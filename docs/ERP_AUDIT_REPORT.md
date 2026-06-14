# تقرير تحليل شامل لنظام ERP — Supermarket POS / Mini-ERP

**تاريخ التحليل:** 2026-06-12
**النظام:** Laravel 12 Mini-ERP (POS + محاسبة + مخزون + موارد بشرية) — متعدد الفروع
**المنهجية:** تحليل معماري + تتبع تدفقات البيانات الفعلية في الكود (Code-grounded audit)، بدون افتراضات نظرية عامة.

> ملاحظة منهجية: كل النتائج أدناه مستندة لقراءة فعلية للكود (controllers / services / models / migrations). أي بند غير مؤكد بالكود مذكور صريحًا كـ "يحتاج فحص إضافي".

---

## 1. الفهم المعماري للنظام (System Architecture)

### 1.1 نوع النظام
**Modular Monolith** (Laravel 12 / MySQL / Blade Server-Rendered) — تطبيق واحد، قاعدة بيانات واحدة، بدون APIs خارجية أو microservices. كل الوحدات تشترك في نفس الـ ORM والمعاملات (`DB::transaction`)، وهو ما يبسّط الاتساق المحاسبي (ACID على مستوى DB واحدة) لكنه يجعل أي تخفّف في الانضباط داخل أي Controller يؤثر مباشرة على دفتر الأستاذ العام.

### 1.2 الوحدات (Modules) — مبنية على 65 Model

| Module | Models الأساسية |
|---|---|
| **POS / Sales** | Sale, SaleItem, SaleReturn, SaleReturnItem, SalesOrder, SalesQuotation, CashShift, PosTerminal |
| **Purchases** | Purchase, PurchaseItem, PurchaseReturn, PurchaseOrder |
| **Inventory** | Product, StockLevel, StockMovement, InventoryAdjustment, InventorySession, StockTransfer, InterBranchTransfer, CostPriceHistory, Warehouse |
| **Customers/Suppliers** | Customer, CustomerDeposit, CustomerPayment, Supplier, SupplierPayment |
| **Vouchers** | ReceiptVoucher, PaymentVoucher |
| **Accounting/GL** | Account, JournalEntry, JournalEntryLine, AccountingPeriod, ExpenseInvoice, ExpensePayment, Reversal |
| **HR/Payroll** | Employee, Attendance, Shift, PayrollRun, EmployeeLoan, EmployeeLeave, EosbProvision |
| **Fixed Assets** | FixedAsset, FixedAssetCategory, AssetDepreciationEntry |
| **Branches/Setup** | Branch, Warehouse, PriceList, Budget, CostCenter |
| **Admin/Security** | User, Role/Permission (Spatie), Setting, AuditLog |

### 1.3 العزل متعدد الفروع (Multi-Branch Isolation)
- كل الجداول المالية الأساسية (`journal_entries`, `sales`, `purchases`, `vouchers`, `payroll_runs`, ...) تحمل `branch_id` (migration `2026_06_02_180759_add_branch_id_to_financial_tables`).
- العزل **ليس عبر Global Scope** بل عبر دالة `effectiveBranchId()` في `app/Http/Controllers/Controller.php:42` تُستخدم يدويًا في كل Controller.
- منطق الصلاحية (`GLOBAL_ROLES = ['admin','manager','reversal_manager']`): هذه الأدوار ترى **كل الفروع دائمًا** بدون قفل، حتى لو كانت مرتبطة بفرع معيّن.
- عند إنشاء فرع جديد، تُنشأ تلقائيًا حسابات نقدية/بنكية مخصّصة له (`BranchAccountingService::setupAccounts()`).

### 1.4 مصادر البيانات والتكاملات الخارجية
- **ZKTeco** (`app/Services/ZKTecoService.php`): تكامل بصمة عبر IP/بروتوكول مملوك — مزامنة الحضور والانصراف تلقائيًا إلى `Attendance`.
- **mPDF** (`PdfService.php`): توليد PDF بدعم RTL/عربي للفواتير والتقارير.
- **لا يوجد**: تكامل بنكي مباشر، تكامل ضريبي (e-invoicing/ETA)، أو API خارجي لتجارة إلكترونية. هذه نقطة مهمة عند تقييم "Integration Testing" (القسم 8) — معظم سيناريوهات التكامل الكلاسيكية لـ ERP **غير منطبقة** على هذا النظام حاليًا.

---

## 2. تحليل تدفق البيانات (Data Flow Analysis)

### 2.1 Order-to-Cash (O2C)
**المسار:** POS (`PosController::store`) → `Sale` + `SaleItem[]` → `WarehouseService::out()` → `LedgerPostingService::postSale()` → التقارير.

| الخطوة | التفاصيل |
|---|---|
| Input | سلة POS (منتجات، كميات، طريقة دفع: نقدي/بنك/AR/رصيد إيداع) |
| Process | `DB::transaction` (PosController.php:192-309) → `Sale::create` → `SaleItem::create` (يحفظ `cost_price` snapshot وقت البيع) → `WarehouseService::out()` (مع `lockForUpdate`) |
| Posting | `postSale()` (LedgerPostingService.php:156-279):<br>• مدين: نقدية/بنك (بيع نقدي) أو ذمم عملاء AR-1200 (بيع آجل) أو سُلَف عملاء 2050 (دفع من الرصيد)<br>• مدين: خصم مبيعات 4300 (إن وجد خصم)<br>• دائن: إيراد مبيعات 4000 (= subtotal)<br>• دائن: ضريبة مستحقة 2200 (إن وجدت)<br>• قيد تكلفة: مدين تكلفة البضاعة المباعة 5000 / دائن مخزون 1300 = Σ(qty × cost_price المُسجّل وقت البيع) |
| Output | `sale.is_posted = true`, القيد يظهر في Trial Balance / Income Statement فورًا (نفس الـ transaction) |

**ملاحظة AVCO:** `cost_price` المستخدم في COGS هو القيمة **اللحظية** للمنتج وقت البيع (snapshot على `SaleItem`)، وهو محدَّث بالـ AVCO من آخر عملية شراء. هذا صحيح محاسبيًا (IAS 2).

### 2.2 Procure-to-Pay (P2P)
**المسار:** `PurchaseController::store` → `Purchase` + `PurchaseItem[]` → AVCO (في `PurchaseItem::boot()`) → `WarehouseService::in()` → `LedgerPostingService::postPurchase()`.

| الخطوة | التفاصيل |
|---|---|
| Process | `DB::transaction` (PurchaseController.php:137-177). عند `PurchaseItem::created()` (PurchaseItem.php:32-92): يُحسب `AVCO = (oldQty×oldCost + newQty×newCost) / (oldQty+newQty)` **قبل** تنفيذ `WarehouseService::in()` — مهم لأن `product->quantity` لازال القديمة. يُسجَّل `CostPriceHistory` (method='avco') و `StockMovement` (مع lot_number/expiry_date). |
| Posting | `postPurchase()` (LedgerPostingService.php:431-490):<br>• مدين: مخزون 1300 = total_amount<br>• دائن: ذمم موردين AP-2000 (الجزء غير المدفوع)<br>• دائن: نقدية 1000 (الجزء المدفوع) |
| دفع المورد لاحقًا | `postSupplierPayment()`: مدين AP-2000 / دائن نقدية أو بنك |

### 2.3 Record-to-Report (R2R)
- كل القيود (`JournalEntry` + `JournalEntryLine[]`) تمر عبر بوّابة واحدة: `LedgerPostingService::buildEntry()` (LPS.php:114-141).
- **Trial Balance** (`TrialBalanceController.php:30-76`): `SUM(debit)/SUM(credit)` لكل حساب حتى `as_of_date`، مفلترة بـ `branch_id`، مع تطبيق قاعدة الرصيد الطبيعي (مدين للأصول/المصروفات، دائن للالتزامات/حقوق الملكية/الإيرادات)، وفحص `|ΣDebit − ΣCredit| < 0.01`.
- **Year-End Closing** (`postYearEndClosing()` LPS.php:1266-1362): يُقفل الإيرادات والمصروفات إلى الأرباح المبقاة 3100 على دفعات.

### 2.4 Inventory-to-Accounting (I2A)

| العملية | تأثير المخزون | تأثير الدفاتر |
|---|---|---|
| **تسوية مخزون** (Adjustment) | `WarehouseService::in/out` | عجز → مدين 6510 (عجز مخزون) / دائن 1300؛ فائض → مدين 1300 / دائن 4100 (فائض مخزون) |
| **مرتجع مبيعات** | عكس خصم المخزون | مدين مردودات مبيعات 4200 / دائن نقدية أو AR؛ + مدين مخزون 1300 / دائن COGS 5000 |
| **مرتجع مشتريات** | خصم من المخزون | مدين نقدية/AP / دائن مخزون 1300 |
| **تحويل مخزني بين مستودعات (Stock Transfer)** | `out()` ثم `in()` | **لا يوجد قيد محاسبي** (بالتصميم — موضّح صريحًا في الكود StockTransferController.php:151) |
| **تحويل بين فروع (Inter-Branch Transfer)** | حركة مخزون + محاسبة | قيدان منفصلان (من/إلى) باستخدام حسابات "مديونية بينية" Due-From/Due-To (`postInterBranchTransfer` LPS.php:1182-1243) |

---

## 3. الضوابط المحاسبية (Accounting Controls)

### 3.1 Double-Entry Validation
- **نقطة التحقق المركزية الوحيدة**: `buildEntry()` — أي استدعاء لأي `post*()` يمر عبرها. الفحص: `abs($debits - $credits) > 0.005` ⇒ `RuntimeException("القيد غير متوازن")`.
- ✅ نقطة قوة حقيقية: 18+ دالة ترحيل مختلفة، كلها تُجمَّع عبر بوابة واحدة.

### 3.2 Period Locking
- `PeriodLockService::assertOpen($entryDate)` يُستدعى **داخل** `buildEntry()` (LPS.php:118) — أي قيد جديد لتاريخ في فترة مقفلة (`AccountingPeriod.status='locked'`) يُرفض فورًا.
- القفل على مستوى (year, month) فريد (unique)، مع دعم قفل سنة كاملة (`lockYear`).

### 3.3 Trial Balance Consistency
- يُحسب مباشرة من `journal_entry_lines` (لا cache منفصل قد يتعارض) → اتساق تلقائي بحكم البناء.

### 3.4 Audit Trail
- `AuditLog` model (user_id, auditable_type/id, action, old/new values JSON, ip).
- التغطية: **غير شاملة** — observers مفعّلة على Sale وPurchase والـ Account والـ Reversal والـ Settings فقط. تعديلات أخرى (مثل تعديل فاتورة مشتريات بعد الترحيل، أو حذف Voucher) قد لا تُسجَّل في AuditLog (يحتاج فحص إضافي لكل Controller).

### 3.5 منع التكرار (Duplicate Posting Prevention)
- DB-level: `journal_entries.entry_number` UNIQUE + composite UNIQUE `(source_type, source_id, reference)`.
- App-level: أعلام `is_posted` على Sale/Purchase تمنع إعادة الترحيل.
- تنسيق رقم القيد يُولَّد تلقائيًا بعد الإدراج (`JournalEntry::boot()`, نمط PENDING→JE-YYYYMMDD-NNNNNN) متوافق مع توجيه CLAUDE.md.

### 3.6 ⚠️ ثغرة جوهرية: قيد الرواتب يتجاوز بوابة الترحيل المركزية
**الموقع:** `app/Http/Controllers/PayrollController.php:198-210`

عند اعتماد مسير الرواتب، يُنشأ `JournalEntry::create([...])` و `JournalEntryLine::create([...])` **مباشرة**، وليس عبر `LedgerPostingService::postX()` / `buildEntry()`.

**الأثر:**
1. **لا يوجد فحص `PeriodLockService::assertOpen()`** — يمكن اعتماد واعتراد مسير رواتب لتاريخ في فترة محاسبية **مقفلة**، بينما كل العمليات الأخرى (مبيعات/مشتريات/سندات) تُرفض في نفس الحالة. هذا تناقض رقابي مباشر (Posting rules enforcement غير متجانسة بين الوحدات).
2. **لا يوجد `branch_id`** في الـ `JournalEntry::create` — القيد يُحفظ بـ `branch_id = NULL`. بما أن Trial Balance/التقارير المالية تُفلتر بـ `je.branch_id = selected_branch`، فإن **مصروف الرواتب لن يظهر في القوائم المالية الخاصة بأي فرع**، فقط في التقارير المُجمَّعة (إن كانت لا تستخدم نفس الفلتر) — احتمال كبير لعدم توازن "مصروف الرواتب" بين تقرير الفرع والتقرير الموحّد.
3. الفحص اليدوي للتوازن (`abs($debits-$credits) > 0.005`) موجود (سطر 194-196) — جيد، لكنه **تكرار منطق** بدلاً من إعادة استخدام `buildEntry()`، وهو مخالفة لقاعدة "نقطة واحدة للترحيل" المذكورة في القسم 3.1.

**التصنيف:** Critical — Finance-impacting، يؤثر على R2R وعلى التحكم في إقفال الفترات.
**التوصية:** إضافة دالة `LedgerPostingService::postPayrollRun()` تبني الأسطر نفسها لكن تمرّ عبر `buildEntry()` (مع تمرير `branch_id` من `$payrollRun->branch_id` أو من فرع الموظف).

### 3.7 ⚠️ تصادم أكواد الحسابات الافتراضية (4150 / 6520)
من جدول `Setting`:
- `account_asset_gain_code` = 4150 **و** `account_pos_cash_overage_code` = 4150 (نفس الكود)
- `account_asset_loss_code` = 6520 **و** `account_pos_cash_shortage_code` = 6520 (نفس الكود)

**الأثر:** إذا لم يُعدِّل العميل هذه الإعدادات الافتراضية، فإن "فائض النقدية في الورديات" و"أرباح بيع الأصول الثابتة" سيُسجَّلان في **نفس حساب GL** — وبالمثل "عجز النقدية" و"خسائر بيع الأصول". هذا يُصعّب التحليل المالي (لا يمكن فصل بند تشغيلي يومي عن بند استثنائي نادر) ويُخفي اتجاهات العجز/الفائض المتكرر في الورديات داخل بند "أرباح/خسائر استثنائية".
**التصنيف:** Medium (Configuration risk وليس خطأ كود) — يحتاج تنبيه عند الإعداد الأولي أو فصل الأكواد الافتراضية.

---

## 4. تحليل الصلاحيات والأمان (RBAC / SoD)

### 4.1 البنية
- Spatie/Permission، 26 مجموعة صلاحيات (~100 صلاحية)، 5 أدوار مبدئية: Admin, Manager, Cashier, Accountant, Reversal Manager.
- فصل واضح بين `*.create` و `*.post` و `*.reverse` (مثلًا `sales.create` ≠ `sales.post` ≠ `sales.reverse`) — هذا يدعم Segregation of Duties نظريًا.

### 4.2 ⚠️ سيناريو اختراق صلاحيات محتمل: "Manager" كـ Global Role
`Controller::GLOBAL_ROLES = ['admin', 'manager', 'reversal_manager']` (Controller.php:27).

بحسب التعليق في الكود: *"Admin & Manager: Company-level authority → see everything"*. لكن في نظام متعدد الفروع، عادةً ما يكون "مدير فرع" (Branch Manager) دورًا **محدودًا بفرعه**، لا دورًا عامًّا على مستوى الشركة. إن كان seeding النظام يُنشئ مستخدمين بدور "Manager" لكل فرع (مدير فرع القاهرة، مدير فرع الإسكندرية)، فإن **كل منهم يرى ويُعدِّل بيانات كل الفروع الأخرى** بحكم `GLOBAL_ROLES`، وهو ما يخالف "Multi-branch isolation" المُعلَن في القسم 1.3.

**التصنيف:** High (Security/SoD) — **يحتاج تأكيد**: هل دور "Manager" مخصَّص فقط لمدير عام واحد على مستوى الشركة (مقبول)، أم يُستخدم كقالب لإنشاء "مدير فرع" متعدد النسخ (ثغرة)؟ التوصية: إضافة دور منفصل "branch_manager" غير مُدرَج في `GLOBAL_ROLES`، يُستخدم لمديري الفروع، ويبقى "Manager" للإدارة العليا فقط.

### 4.3 Approval Workflows
- `sales.post` / `purchases.post` / `accounting.post` منفصلة عن `*.create` — تسمح بفصل "من ينشئ المستند" عن "من يُرحِّله محاسبيًا"، **لكن** عمليًا `postSale()` يُستدعى تلقائيًا داخل نفس transaction لحظة إنشاء البيع في POS (PosController.php:307) — أي **لا يوجد فعليًا خطوة موافقة منفصلة لمبيعات POS**؛ صلاحية `sales.post` قد تكون بلا أثر عملي لمسار POS العادي (يحتاج فحص: هل تُستخدم في سياق آخر مثل فواتير مبيعات يدوية SaleController منفصل عن PosController؟).
- Reversal: مسموح فقط لأنواع محددة (Sale, Purchase, ReceiptVoucher, PaymentVoucher, PayrollRun, InventoryAdjustment) مع حارس "منع العكس المزدوج" — جيد.

### 4.4 Multi-Branch Isolation — تقييم
| الحالة | السلوك | تقييم |
|---|---|---|
| Admin/Manager/Reversal Manager | يرى كل الفروع، فلتر اختياري | ✅ لكن انظر 4.2 |
| موظف مرتبط بفرع (cashier/accountant) | مُقيَّد بفرعه، لا يمكن تغييره | ✅ |
| موظف بدون فرع (محاسب عام) | يرى الكل، فلتر اختياري | ✅ مقصود |
| **القيود اليدوية (Manual Journal Entries) عبر `/journal-entries`** | لم يُفحص: هل تُفرض `branch_id` تلقائيًا حسب المستخدم، أم يمكن لمحاسب فرع إدخال قيد بـ `branch_id` فرع آخر؟ | **يحتاج فحص** — إن لم يُفرض، فهذا اختراق عزل بيانات |

---

## 5. استراتيجية الاختبار (QA Strategy) — مُركّزة على المخاطر المكتشفة

نظرًا لطبيعة Modular Monolith بدون APIs خارجية، تُعدَّل الاستراتيجية الكلاسيكية كالتالي:

| نوع الاختبار | الأولوية | التركيز |
|---|---|---|
| **Unit Testing** | High | `LedgerPostingService::buildEntry()` (التوازن)، `PeriodLockService::assertOpen()`، AVCO calculation في `PurchaseItem::boot()` |
| **Financial Reconciliation** | Critical | مطابقة `stock_levels` vs `products.quantity`، مطابقة COGS مع AVCO، مطابقة Trial Balance لكل فرع مع التقرير الموحّد (خاصة بعد إصلاح 3.6) |
| **Integration Testing** | Low | محدودة — فقط ZKTeco sync (لا APIs بنكية/ضريبية فعليًا) |
| **System/E2E Testing** | Critical | دورة Purchase→Sale→Return→Payment→Closing كاملة (القسم 7) |
| **Regression** | High | كل تعديل على `LedgerPostingService` أو `WarehouseService` يجب أن يُعاد تشغيله على دورة E2E كاملة لأن كل الوحدات تعتمد عليهما |
| **Security/SoD** | Critical | اختبارات 4.2 و4.4 أعلاه |
| **Data Migration** | N/A | لا توجد عملية ترحيل بيانات من نظام خارجي حاليًا |

---

## 6. Test Cases تفصيلية (الدورة المالية الأساسية)

| ID | Module | Scenario | Preconditions | Steps | Expected Result | Accounting Impact (Dr/Cr) | Priority |
|---|---|---|---|---|---|---|---|
| TC-O2C-01 | POS | بيع نقدي عادي | منتج برصيد ≥ الكمية، فترة مفتوحة | إنشاء بيع نقدي بمنتج × كمية × سعر | `sale.is_posted=true`, JE متوازن, stock_levels−=qty | Dr 1000 (Cash)=Total; Dr 5000 (COGS)=qty×cost; Cr 4000 (Sales)=subtotal; Cr 2200 (Tax) إن وجد; Cr 1300 (Inventory)=qty×cost | Critical |
| TC-O2C-02 | POS | بيع آجل (Credit) | عميل له حساب AR | بيع بدون دفع نقدي، `is_credit=true` | رصيد AR للعميل يزيد بالـ total | Dr 1200 (AR)=Total; Cr 4000; + قيد COGS كما أعلاه | Critical |
| TC-O2C-03 | POS | دفع من رصيد الإيداع (Deposit Balance) | للعميل رصيد إيداع كافٍ ≥ المبلغ | اختيار `deposit_balance` كطريقة دفع | رصيد إيداع العميل ينخفض | Dr 2050 (Customer Deposits)=balance_used; Cr 4000 | High |
| TC-O2C-04 | POS | بيع مع خصم | — | إدخال نسبة/قيمة خصم | الخصم يُسجَّل في 4300 | Dr 4300 (Sales Discount)=discount; باقي القيد كالعادة | Medium |
| TC-O2C-05 | Sales Return | مرتجع بيع نقدي | فاتورة بيع مرحَّلة سابقًا | إنشاء مرتجع لكامل/جزء من الفاتورة | المخزون يرتفع، COGS يُعكس | Dr 4200 (Sales Returns); Cr 1000/1200; + Dr 1300 / Cr 5000 (عكس التكلفة) | High |
| TC-P2P-01 | Purchases | فاتورة شراء بدفع جزئي | مورد موجود، فترة مفتوحة | شراء كمية بسعر وحدة، دفع جزء نقدًا | `cost_price` للمنتج يُحدَّث بـ AVCO، `CostPriceHistory` سجل جديد method='avco' | Dr 1300 (Inventory)=total; Cr 2000 (AP)=unpaid; Cr 1000 (Cash)=paid | Critical |
| TC-P2P-02 | Purchases | AVCO عبر شراءين متتاليين | منتج برصيد سابق qty₁@cost₁ | شراء qty₂@cost₂ | `new_cost = (qty₁×cost₁+qty₂×cost₂)/(qty₁+qty₂)` بدقة 4 أرقام عشرية | تحقق رقمي فقط (لا قيد مباشر، التأثير على COGS لاحقًا) | Critical |
| TC-P2P-03 | Suppliers | دفع لمورد | فاتورة شراء بها AP رصيد | تسجيل دفعة جزئية/كاملة | رصيد AP ينخفض | Dr 2000 (AP)=amount; Cr 1000/1100 | High |
| TC-P2P-04 | Purchase Return | مرتجع شراء | فاتورة شراء مرحَّلة | إنشاء مرتجع | المخزون ينخفض | Dr 1000/2000 (Cash أو تخفيض AP); Cr 1300 | Medium |
| TC-I2A-01 | Inventory Adjustment | عجز مخزون | جرد فعلي < النظام | تسجيل تسوية بالعجز | `journal_entry_id` على الـ Adjustment | Dr 6510 (Inventory Shortage); Cr 1300 | High |
| TC-I2A-02 | Inventory Adjustment | فائض مخزون | جرد فعلي > النظام | تسجيل تسوية بالفائض | — | Dr 1300; Cr 4100 (Inventory Surplus) | High |
| TC-I2A-03 | Stock Transfer | تحويل بين مستودعين بنفس الفرع | — | تحويل كمية | لا قيد محاسبي يُنشأ (بالتصميم) — تحقق من **عدم** إنشاء JournalEntry | لا تأثير على GL | Medium |
| TC-I2A-04 | Inter-Branch Transfer | تحويل بين فرعين | فرعان لهما حسابات Due-From/Due-To | تحويل بضاعة من فرع A إلى B | قيدان منفصلان (A وB) | فرع A: Dr Due-From-B / Cr Inventory-A; فرع B: Dr Inventory-B / Cr Due-To-A | High |
| TC-R2R-01 | Period Lock | منع الترحيل في فترة مقفلة | قفل شهر معيّن من `/accounting-periods` | محاولة بيع/شراء بتاريخ داخل الفترة المقفلة | **رفض العملية** برسالة "الفترة المحاسبية مقفلة" | لا قيد يُنشأ | Critical |
| TC-R2R-02 | **Payroll vs Period Lock** ⚠️ | اعتماد مسير رواتب بتاريخ في فترة مقفلة | قفل الشهر، إنشاء PayrollRun بتاريخ ضمنه | اعتماد المسير | **متوقع حاليًا: يُسمح بالترحيل (ثغرة 3.6)** — يجب أن يُرفض كباقي العمليات | Dr 6200 (Salaries) / Cr 1000 + 2100... | Critical |
| TC-R2R-03 | **Payroll Branch Filter** ⚠️ | بعد TC-R2R-02 | عرض Trial Balance لفرع معيّن مقابل التقرير الموحّد | فحص ظهور قيد الرواتب | **متوقع: القيد بـ branch_id=NULL لا يظهر في تقرير الفرع** — عدم تطابق مع التقرير الموحّد | يكشف الفجوة في 3.6 | Critical |
| TC-R2R-04 | Trial Balance | التوازن العام | بعد أي مجموعة عمليات | فتح Trial Balance | `ΣDebit = ΣCredit` لكل التقرير | `isBalanced=true` | Critical |
| TC-R2R-05 | Reversal | عكس فاتورة بيع مرحَّلة | بيع مرحَّل، صلاحية `sales.reverse` | إنشاء Reversal | قيد جديد بمرجع `REV-{original}` بأسطر مقلوبة Dr↔Cr، `is_reversed=true` على الأصل | عكس كامل لقيد TC-O2C-01 | High |
| TC-R2R-06 | Reversal | منع العكس المزدوج | فاتورة معكوسة مسبقًا (TC-R2R-05) | محاولة عكسها مرة أخرى | رفض — "already reversed" | لا قيد جديد | High |
| TC-SEC-01 | RBAC | مستخدم Cashier يحاول الوصول لـ Trial Balance | مستخدم بدور Cashier فقط | فتح `/accounting/trial-balance` | 403 Forbidden | — | Critical |
| TC-SEC-02 | Branch Isolation | محاسب فرع A يحاول عرض بيانات فرع B | مستخدم `branch_id=A`, ليس Global Role | تغيير `branch_id` في query string لطلب فرع B | تُرجَّع بيانات فرع A فقط (يتم تجاهل الفلتر المُرسَل) | — | Critical |
| TC-SEC-03 | RBAC | دور "Manager" مرتبط بفرع معيّن | مستخدم role=manager, branch_id=A | محاولة الوصول لتقارير/مبيعات فرع B | **متوقع حاليًا: مسموح (GLOBAL_ROLES) — يحتاج تأكيد إن كان مقصودًا (انظر 4.2)** | — | High |
| TC-HR-01 | Payroll | استقطاع سلفة موظف ضمن مسير الرواتب | موظف له `EmployeeLoan` نشط، `remaining_balance>0` | اعتماد مسير يشمل قسط السلفة | `remaining_balance` ينخفض FIFO، `status='settled'` إن وصل صفر | Cr 1250 (Employee Loans)=loan_deduction (ضمن قيد الرواتب) | Medium |
| TC-CFG-01 | Settings | تصادم أكواد الحسابات 4150/6520 | تثبيت جديد، إعدادات افتراضية | تسجيل عجز/فائض وردية + بيع أصل بربح/خسارة | تحقق هل تظهر القيم في **نفس** حساب GL أم حسابات مختلفة | يكشف ثغرة 3.7 | Medium |

---

## 7. محاكاة دورة مالية كاملة (E2E Simulation)

**السيناريو:** `شراء بضاعة (آجل) → بيع جزء منها (نقدي) → مرتجع بيع جزئي → دفع للمورد → إقفال وردية → عرض في القوائم المالية`

1. **شراء 100 وحدة @ 10 ج.م** (AP بالكامل):
   - Dr 1300 (Inventory) = 1000 / Cr 2000 (AP) = 1000
   - `product.cost_price` يُحدَّث AVCO → إن كان الرصيد السابق صفر، `cost_price = 10`
2. **بيع 30 وحدة @ 15 ج.م نقدًا** (بدون خصم/ضريبة):
   - Dr 1000 (Cash) = 450 / Cr 4000 (Sales) = 450
   - Dr 5000 (COGS) = 300 (30×10) / Cr 1300 (Inventory) = 300
   - `stock_levels.quantity` = 70
3. **مرتجع 10 وحدات من البيع أعلاه (نقدًا)**:
   - Dr 4200 (Sales Returns) = 150 / Cr 1000 (Cash) = 150
   - Dr 1300 (Inventory) = 100 (10×10) / Cr 5000 (COGS) = 100
   - `stock_levels.quantity` = 80
4. **دفع 1000 ج.م للمورد**:
   - Dr 2000 (AP) = 1000 / Cr 1000 (Cash) = 1000 → رصيد AP = صفر
5. **إقفال الوردية** (مطابقة الكاش الفعلي مع المتوقع):
   - المتوقع نقدًا = 450 (بيع) − 150 (مرتجع) − 1000 (دفع مورد) = −700 (سحب نقدي صافي من الصندوق — افتراضي للتوضيح)
   - أي فرق (عجز/فائض) → 6520 أو 4150 (انتبه لتصادم 3.7)

**فحوصات التحقق (Reconciliation Checks):**
| البند | القيمة المتوقعة |
|---|---|
| `products.quantity` بعد كل الخطوات | 80 |
| `Σstock_levels.quantity` لكل المستودعات لهذا المنتج | = 80 (مطابقة مع products.quantity) |
| Trial Balance — حساب 1300 (Inventory) | 1000 (شراء) − 300 (COGS بيع) + 100 (عكس COGS مرتجع) = **800** = 80 وحدة × 10 ج.م ✅ |
| Trial Balance — حساب 4000 (Sales) net | 450 (إيرادات) — لا يُخصم المرتجع من 4000 بل يُسجَّل في 4200 (مردودات) — **Income Statement يجب أن يعرض `Net Sales = 4000 − 4200`** — يحتاج تأكيد أن `FinancialStatementService` يطبّق هذا الطرح |
| Cash (1000) صافي الحركة | +450 − 150 − 1000 = −700 |
| `isBalanced` على كل القيود | true |

**نقاط الفشل المحتملة (يجب اختبارها فعليًا):**
- هل `FinancialStatementService` يحسب "صافي المبيعات" بطرح 4200 من 4000، أم يعرضهما كبندين منفصلين بدون طرح صريح؟ (يحدد دقة Income Statement)
- هل COGS المُعاد (Reversal من المرتجع) يستخدم `cost_price` **وقت البيع الأصلي** (المخزَّن في SaleItem) أم `cost_price` الحالي للمنتج (الذي قد يكون تغيّر بسبب AVCO لشراء جديد بين البيع والمرتجع)؟ — إن استُخدم السعر الحالي، فالقيد 3 أعلاه سيكون غير دقيق.

---

## 8. اختبار التكامل (Integration Testing)

| التكامل | الحالة | الملاحظات |
|---|---|---|
| ERP ↔ Banking | **غير موجود** | لا APIs بنكية. أي "حساب بنك" هو حساب GL داخلي فقط (1100). |
| ERP ↔ Tax/E-invoicing | **غير موجود** | `account_tax_payable_code` موجود لكنه حساب محاسبي فقط، لا تكامل مع مصلحة الضرائب. |
| ERP ↔ POS Hardware | جزئي | لا تكامل ESC/POS مباشر في `app/Services` — الإيصالات PDF عبر mPDF. إن كانت الطباعة الحرارية تتم عبر JS/المتصفح (خارج Laravel)، فهي خارج نطاق هذا التحليل الخلفي. |
| ERP ↔ Fingerprint (ZKTeco) | ✅ موجود | `ZKTecoService` يقرأ سجلات الجهاز عبر IP ثابت (192.168.1.100:4370 hardcoded افتراضيًا؟ — **يحتاج تأكيد أن العنوان قابل للتهيئة من Settings وليس مُثبَّتًا بالكود**، وإلا فهو خطر تشغيلي عند تعدد الفروع/الأجهزة). |
| ERP ↔ Inventory (داخلي) | ✅ | متّسق عبر `WarehouseService` مع `lockForUpdate`. |

**خطر التزامن (Concurrency):** `WarehouseService::out()` يستخدم `lockForUpdate` — جيد لمنع Race Condition عند بيع نفس المنتج من POS terminals متعددة في آنٍ واحد. **لم يُفحص**: هل `PurchaseItem::boot()` (AVCO) و`WarehouseService::in()` يحدثان داخل نفس قفل الصف لمنع تضارب AVCO عند استلام شراءين لنفس المنتج بالتوازي.

---

## 9. التحليل المالي النهائي (Financial Integrity Review)

| المعيار | التقييم | الأساس |
|---|---|---|
| **دقة القيود (Double Entry)** | ✅ قوي | بوابة `buildEntry()` موحّدة مع فحص توازن صارم (≤0.005) |
| **اتساق الترصيد (Trial Balance)** | ✅ قوي بنيويًا، ⚠️ مع تحفظ | يُحسب مباشرة من journal_entry_lines؛ **لكن** قيد الرواتب (3.6) قد يُنتج فروقات بين تقرير فرع وتقرير موحّد |
| **ضبط الفترات (Period Control)** | ⚠️ غير متجانس | فعّال لكل العمليات إلا الرواتب (3.6) |
| **منع التكرار/الازدواج** | ✅ قوي | UNIQUE constraints + `is_posted` + `is_reversed` |
| **عزل الفروع (Branch Isolation)** | ⚠️ يحتاج تأكيد | منطق سليم للأدوار العادية، لكن "Manager" كـ Global Role قد يكسر العزل إن استُخدم كقالب لمدير فرع (4.2) |
| **تتبع التكلفة (AVCO)** | ✅ سليم منطقيًا | الصيغة صحيحة، التوقيت صحيح (قبل تحديث الكمية)، التسجيل التاريخي موجود (CostPriceHistory) |
| **مستوى المخاطر العام** | **متوسط-مرتفع** | المخاطر مركّزة في نقطتين فقط (Payroll posting gap + Manager global role) بدلاً من كونها منتشرة — سهل الإصلاح نسبيًا |

---

## 10. التقرير النهائي والتوصيات (Final Audit Report)

### نقاط القوة (Strengths)
1. بوابة ترحيل GL مركزية واحدة (`buildEntry`) مع فحص توازن وقفل فترات — تصميم أفضل من كثير من الأنظمة المخصصة.
2. AVCO مُطبَّق بشكل صحيح زمنيًا ومُوثَّق تاريخيًا (`CostPriceHistory`).
3. حماية متعددة الطبقات من التكرار (DB constraints + app flags).
4. آلية Reversal منضبطة بقائمة بيضاء من الأنواع المسموحة + منع العكس المزدوج.

### الأخطاء الحرجة (Critical, Finance-Impacting)
| # | المشكلة | الموقع | الأثر |
|---|---|---|---|
| 1 | قيد الرواتب يتجاوز `buildEntry()` — لا فحص قفل فترة، و `branch_id=NULL` | `PayrollController.php:198-210` | يسمح بترحيل رواتب في فترة مقفلة؛ يُخفي مصروف الرواتب عن التقارير المالية الخاصة بالفروع |
| 2 | دور "Manager" كـ Global Role قد يكسر عزل الفروع إن استُخدم لمديري فروع متعددين | `Controller.php:27` | اختراق محتمل لعزل البيانات بين الفروع |

### مخاطر التهيئة (Configuration Risks)
| # | المشكلة | الأثر |
|---|---|---|
| 3 | تصادم أكواد الحسابات الافتراضية 4150 (فائض ورديات = ربح بيع أصول) و6520 (عجز ورديات = خسارة بيع أصول) | اختلاط بنود تشغيلية يومية ببنود استثنائية في القوائم المالية |

### بنود تحتاج فحصًا إضافيًا (Open Items — لم تُحسم بالكود المقروء)
4. هل القيود اليدوية (`/journal-entries`) تفرض `branch_id` المستخدم تلقائيًا أم قابلة للتزييف؟
5. هل `FinancialStatementService` يطرح 4200 (مردودات المبيعات) من 4000 عند حساب "صافي المبيعات" في Income Statement؟
6. عند مرتجع بيع، هل تُستخدم `cost_price` المسجّلة وقت البيع الأصلي (snapshot) أم `cost_price` الحالي للمنتج لعكس COGS؟
7. عنوان IP لجهاز ZKTeco — مُهيَّأ من Settings أم مُثبَّت بالكود؟
8. تغطية AuditLog — هل تشمل تعديل/حذف المستندات المُرحَّلة (Sale/Purchase بعد `is_posted=true`)؟

### خطة التحسين المقترحة (الأولوية)
1. **(Critical, فوري)** إنشاء `LedgerPostingService::postPayrollRun()` تُمرّر عبر `buildEntry()` مع `branch_id` صحيح — يلغي الفجوتين #1 معًا.
2. **(High)** مراجعة قائمة المستخدمين بدور "Manager": إن وُجد أكثر من مستخدم بهذا الدور مرتبط بفروع مختلفة، إنشاء دور `branch_manager` منفصل غير مُدرَج في `GLOBAL_ROLES`.
3. **(Medium)** فصل الأكواد الافتراضية: `account_pos_cash_overage_code`/`account_pos_cash_shortage_code` يجب أن تكون أكواد مختلفة عن `account_asset_gain_code`/`account_asset_loss_code` (مثلاً 4160/6530).
4. **(Medium)** تنفيذ Test Cases TC-R2R-02، TC-R2R-03، TC-SEC-03، TC-CFG-01 من القسم 6 كاختبارات آلية (Feature Tests في Laravel) لمنع رجوع هذه الثغرات.
5. **(Low)** توثيق البنود 4-8 أعلاه بعد فحصها وإغلاقها كـ "Open Items" في هذا التقرير.

---

*تم إعداد هذا التقرير عبر تحليل مباشر للكود المصدري (Controllers/Services/Models/Migrations) دون الاعتماد على افتراضات نظرية. أي بند مُعلَّم "يحتاج فحص إضافي" يتطلب تنفيذ Test Case مقابل بيئة فعلية لتأكيده.*
