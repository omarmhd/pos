@extends('layouts.app')
@section('title', 'دليل الاستخدام')
@section('page-title', 'دليل الاستخدام والمصطلحات')

@push('styles')
<style>
    .help-toc { position: sticky; top: 80px; }
    .help-toc .nav-link { color: #444; font-size: 0.85rem; padding: 4px 10px; border-right: 2px solid transparent; }
    .help-toc .nav-link:hover { color: #2980b9; border-right-color: #2980b9; }
    .help-toc .nav-link.active { color: #2980b9; border-right-color: #2980b9; font-weight: 600; }
    .help-toc .sub-item { padding-right: 20px !important; font-size: 0.8rem; }

    .help-section { scroll-margin-top: 80px; }
    .help-section + .help-section { margin-top: 2.5rem; }
    .section-header {
        background: linear-gradient(135deg, #2c3e50 0%, #3d5166 100%);
        color: white;
        border-radius: 8px 8px 0 0;
        padding: 14px 20px;
        font-size: 1.05rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .section-body { border: 1px solid #e0e6ed; border-top: none; border-radius: 0 0 8px 8px; padding: 20px; background: white; }
    .subsection-title { font-size: 0.95rem; font-weight: 700; color: #2c3e50; margin: 18px 0 8px; border-bottom: 2px solid #e8f4fd; padding-bottom: 6px; }
    .subsection-title i { color: #3498db; }

    .term-table td:first-child { font-weight: 700; color: #2c3e50; width: 22%; }
    .term-table td:nth-child(2) { color: #555; width: 20%; font-size: 0.85rem; }
    .term-table tr:hover td { background: #f8fbff; }

    .workflow-step {
        display: flex; gap: 14px; align-items: flex-start;
        padding: 10px 0; border-bottom: 1px dashed #e8edf2;
    }
    .workflow-step:last-child { border-bottom: none; }
    .step-num {
        background: #3498db; color: white; border-radius: 50%;
        width: 28px; height: 28px; min-width: 28px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.8rem; font-weight: 700;
    }
    .step-body { flex: 1; }
    .step-title { font-weight: 600; color: #2c3e50; font-size: 0.9rem; margin-bottom: 2px; }
    .step-desc { font-size: 0.83rem; color: #666; }

    .role-badge-admin  { background:#e74c3c; color:#fff; padding:2px 8px; border-radius:4px; font-size:0.75rem; }
    .role-badge-manager{ background:#f39c12; color:#fff; padding:2px 8px; border-radius:4px; font-size:0.75rem; }
    .role-badge-cashier{ background:#27ae60; color:#fff; padding:2px 8px; border-radius:4px; font-size:0.75rem; }

    .gl-example { background: #f7f9fc; border: 1px solid #dce3ea; border-radius: 6px; padding: 12px 16px; }
    .gl-example table td { padding: 3px 10px; font-size: 0.85rem; }
    .gl-debit  { color:#2980b9; font-weight:700; }
    .gl-credit { color:#27ae60; font-weight:700; padding-right:20px; }

    .alert-tip { border-right: 4px solid #3498db; background: #eaf4fd; color: #1a6090; padding: 10px 14px; border-radius: 0 6px 6px 0; font-size: 0.85rem; margin: 10px 0; }
    .alert-warn { border-right: 4px solid #f39c12; background: #fef9e7; color: #7d6608; padding: 10px 14px; border-radius: 0 6px 6px 0; font-size: 0.85rem; margin: 10px 0; }
</style>
@endpush

@section('content')
<div class="row">

    {{-- ── Table of Contents ── --}}
    <div class="col-lg-3 d-none d-lg-block">
        <div class="help-toc card p-3">
            <div class="fw-bold text-muted small text-uppercase mb-2" style="letter-spacing:1px">المحتويات</div>
            <nav class="nav flex-column">
                <a class="nav-link" href="#sec-overview">نظرة عامة على النظام</a>
                <a class="nav-link" href="#sec-roles">الأدوار والصلاحيات</a>
                <a class="nav-link sub-item" href="#sec-roles">└ المدير، المشرف، الكاشير</a>
                <a class="nav-link" href="#sec-pos">نقطة البيع (POS)</a>
                <a class="nav-link" href="#sec-inventory">المخزون والمنتجات</a>
                <a class="nav-link" href="#sec-purchases">المشتريات والموردون</a>
                <a class="nav-link" href="#sec-customers">العملاء والذمم المدينة</a>
                <a class="nav-link" href="#sec-hr">الموارد البشرية والرواتب</a>
                <a class="nav-link" href="#sec-zkteco"><i class="bi bi-fingerprint"></i> ربط جهاز البصمة</a>
                <a class="nav-link" href="#sec-accounting">المحاسبة والقيود</a>
                <a class="nav-link sub-item" href="#sec-accounting">└ القيد المزدوج</a>
                <a class="nav-link sub-item" href="#sec-accounts">└ شجرة الحسابات</a>
                <a class="nav-link sub-item" href="#sec-accounting">└ إقفال نهاية السنة</a>
                <a class="nav-link" href="#sec-reports">التقارير</a>
                <a class="nav-link" href="#sec-settings">إعدادات النظام</a>
                <a class="nav-link sub-item" href="#sec-settings">└ المتجر / العملة / الضريبة</a>
                <a class="nav-link sub-item" href="#sec-settings">└ POS / HR / المخزون / GL</a>
                <a class="nav-link" href="#sec-glossary">قاموس المصطلحات</a>
                <a class="nav-link" href="#sec-workflows">سيناريوهات الاستخدام</a>
            </nav>
        </div>
    </div>

    {{-- ── Content ── --}}
    <div class="col-lg-9">

        {{-- ══════════════════════════════════════════════════════════ --}}
        {{-- 1. Overview --}}
        {{-- ══════════════════════════════════════════════════════════ --}}
        <div class="help-section" id="sec-overview">
            <div class="section-header">
                <i class="bi bi-info-circle-fill fs-5"></i> نظرة عامة على النظام
            </div>
            <div class="section-body">
                <p>
                    هذا النظام هو <strong>نظام ERP متكامل مخصص لتجار التجزئة والمطاعم والسوبر ماركت</strong>.
                    يجمع بين نقطة البيع (POS) والمحاسبة المالية المزدوجة القيد وإدارة الموارد البشرية في بيئة واحدة متكاملة.
                </p>

                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <div class="card border h-100">
                            <div class="card-body">
                                <h6 class="fw-bold text-primary"><i class="bi bi-check2-circle"></i> ما يستطيع النظام تنفيذه</h6>
                                <ul class="mb-0 small text-muted ps-3">
                                    <li>إتمام عمليات البيع نقداً وبالبطاقة والمحفظة والآجل</li>
                                    <li>إدارة المخزون تلقائياً عند كل عملية بيع وشراء</li>
                                    <li>ترحيل قيود محاسبية مزدوجة تلقائياً</li>
                                    <li>إعداد قوائم الدخل والميزانية العمومية من دفتر الأستاذ</li>
                                    <li>احتساب رواتب الموظفين من سجلات الحضور</li>
                                    <li>متابعة ذمم العملاء والموردين مع تحليل التقادم</li>
                                    <li>طباعة فواتير A4 بصيغة PDF وإيصالات حرارية 80mm</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border h-100">
                            <div class="card-body">
                                <h6 class="fw-bold text-secondary"><i class="bi bi-gear"></i> المتطلبات الأساسية للتشغيل</h6>
                                <ul class="mb-0 small text-muted ps-3">
                                    <li>إنشاء شجرة حسابات من قائمة المحاسبة (لمرة واحدة)</li>
                                    <li>ربط أكواد الحسابات في إعدادات المحاسبة</li>
                                    <li>إضافة الفئات والمنتجات قبل بدء البيع</li>
                                    <li>تعريف الورديات قبل تسجيل الحضور</li>
                                    <li>تعريف الموظفين قبل تشغيل مسير الرواتب</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert-tip mt-3">
                    <i class="bi bi-lightbulb-fill"></i>
                    <strong>مسار الإعداد الموصى به:</strong>
                    شجرة الحسابات ← إعدادات النظام (ربط الحسابات) ← الفئات ← المنتجات ← الموردون ← الموظفون ← الورديات ← البدء بالعمل
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════ --}}
        {{-- 2. Roles --}}
        {{-- ══════════════════════════════════════════════════════════ --}}
        <div class="help-section" id="sec-roles">
            <div class="section-header">
                <i class="bi bi-shield-lock-fill fs-5"></i> الأدوار والصلاحيات
            </div>
            <div class="section-body">
                <p class="text-muted small mb-3">يتحكم النظام في الوصول عبر ثلاثة أدوار رئيسية. يُحدد الدور عند إنشاء حساب المستخدم.</p>
                <div class="table-responsive">
                    <table class="table table-bordered small mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>الدور</th>
                                <th>الاسم بالنظام</th>
                                <th>الصلاحيات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="role-badge-admin">مدير النظام</span></td>
                                <td><code>admin</code></td>
                                <td>وصول كامل — إدارة المستخدمين، شجرة الحسابات، الإعدادات، سجل المراجعة، قيود التصحيح</td>
                            </tr>
                            <tr>
                                <td><span class="role-badge-manager">مدير/مشرف</span></td>
                                <td><code>manager</code></td>
                                <td>جميع الوحدات التشغيلية (مبيعات، مشتريات، HR، محاسبة، تقارير) — بدون إدارة مستخدمين أو إعدادات</td>
                            </tr>
                            <tr>
                                <td><span class="role-badge-cashier">كاشير</span></td>
                                <td><code>cashier</code></td>
                                <td>نقطة البيع (POS) فقط</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-secondary">مدير التصحيح</span></td>
                                <td><code>reversal_manager</code></td>
                                <td>إنشاء قيود التصحيح والعكس</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="alert-warn mt-3">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <strong>تنبيه:</strong> مستخدم الـ cashier لا يرى في الشريط الجانبي سوى لوحة التحكم ونقطة البيع.
                    يمكن للمدير تعديل الصلاحيات الفردية من صفحة "الصلاحيات" لمنح أو سحب حق محدد دون تغيير الدور.
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════ --}}
        {{-- 3. POS --}}
        {{-- ══════════════════════════════════════════════════════════ --}}
        <div class="help-section" id="sec-pos">
            <div class="section-header">
                <i class="bi bi-cart-check-fill fs-5"></i> نقطة البيع (POS)
            </div>
            <div class="section-body">
                <h6 class="subsection-title"><i class="bi bi-grid"></i> واجهة البيع</h6>
                <p class="small text-muted">
                    تعرض الصفحة بطاقات المنتجات (مرشَّحة بالفئة أو بالبحث أو بالباركود).
                    بالضغط على منتج يُضاف للسلة. يمكن تعديل الكمية بالأزرار + / – أو بالكتابة المباشرة.
                </p>

                <div class="row g-2 mb-3">
                    <div class="col-sm-6">
                        <div class="card border h-100 p-2">
                            <div class="fw-semibold small mb-1"><i class="bi bi-cash text-success"></i> بيع نقدي</div>
                            <div class="small text-muted">اختر طريقة الدفع (نقد/بطاقة/محفظة)، أدخل المبلغ المستلم، سيحتسب الباقي تلقائياً. انقر "إتمام البيع".</div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="card border h-100 p-2">
                            <div class="fw-semibold small mb-1"><i class="bi bi-credit-card text-warning"></i> بيع آجل</div>
                            <div class="small text-muted">فعّل خيار "بيع آجل"، اختر العميل. سيُترحل المبلغ على ذمة العميل (حساب الذمم المدينة).</div>
                        </div>
                    </div>
                </div>

                <h6 class="subsection-title"><i class="bi bi-keyboard"></i> اختصارات لوحة المفاتيح</h6>
                <div class="table-responsive">
                    <table class="table table-sm small mb-0">
                        <tr><td><kbd>F2</kbd></td><td>إتمام عملية البيع</td></tr>
                        <tr><td><kbd>Ctrl + Z</kbd></td><td>إلغاء آخر منتج مضاف</td></tr>
                        <tr><td>مسح الباركود</td><td>يضيف المنتج تلقائياً عند قراءة الباركود في حقل البحث</td></tr>
                    </table>
                </div>

                <h6 class="subsection-title"><i class="bi bi-printer"></i> الإيصال</h6>
                <p class="small text-muted">
                    بعد إتمام البيع تظهر صفحة الإيصال الحراري (80mm) وتُطبع تلقائياً.
                    من صفحة "المبيعات ← عرض الفاتورة" يمكن تحميل نسخة PDF (A4) من زر <strong>PDF</strong>.
                </p>

                <div class="gl-example">
                    <strong class="small">القيود التلقائية عند البيع النقدي (قيدان منفصلان):</strong>
                    <div class="mt-2 mb-1 text-muted" style="font-size:0.78rem">قيد الإيراد:</div>
                    <table>
                        <tr><td class="gl-debit">من: الصندوق/النقدية (1000)</td><td>← إجمالي الفاتورة</td></tr>
                        <tr><td class="gl-credit">إلى: المبيعات (4000)</td><td>← صافي قيمة البيع</td></tr>
                        <tr><td class="gl-credit">إلى: ضريبة القيمة المضافة (2200)</td><td>← الضريبة (إن وُجدت)</td></tr>
                    </table>
                    <div class="mt-2 mb-1 text-muted" style="font-size:0.78rem">قيد تكلفة البضاعة المباعة (COGS):</div>
                    <table>
                        <tr><td class="gl-debit">من: تكلفة البضاعة المباعة (5000)</td><td>← تكلفة الوحدات المباعة</td></tr>
                        <tr><td class="gl-credit">إلى: المخزون (1300)</td><td>← خفض قيمة المخزون</td></tr>
                    </table>
                    <div class="alert-tip mt-2" style="font-size:0.78rem">
                        <i class="bi bi-info-circle"></i>
                        التكلفة المستخدمة في قيد COGS هي <strong>سعر التكلفة وقت البيع</strong> (مُثبَّت على بند الفاتورة)، لا السعر الحالي للمنتج — يضمن ذلك دقة تاريخية حتى لو تغيّر سعر التكلفة لاحقاً.
                    </div>
                </div>
                <div class="gl-example mt-2">
                    <strong class="small">قيد البيع الآجل (على حساب عميل):</strong>
                    <table class="mt-1">
                        <tr><td class="gl-debit">من: الذمم المدينة (1200)</td><td>← إجمالي الفاتورة</td></tr>
                        <tr><td class="gl-credit">إلى: المبيعات (4000)</td><td>← صافي قيمة البيع</td></tr>
                        <tr><td class="gl-credit">إلى: ضريبة القيمة المضافة (2200)</td><td>← الضريبة (إن وُجدت)</td></tr>
                    </table>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════ --}}
        {{-- 4. Inventory --}}
        {{-- ══════════════════════════════════════════════════════════ --}}
        <div class="help-section" id="sec-inventory">
            <div class="section-header">
                <i class="bi bi-box-seam-fill fs-5"></i> المخزون والمنتجات
            </div>
            <div class="section-body">
                <p class="small text-muted">
                    المنتج هو الوحدة الأساسية للبيع والشراء. كل منتج مرتبط بفئة وله سعر تكلفة وسعر بيع وكمية في المخزون.
                </p>

                <h6 class="subsection-title"><i class="bi bi-list-check"></i> حقول المنتج المهمة</h6>
                <div class="table-responsive">
                    <table class="table table-sm term-table small">
                        <tr><td>سعر التكلفة</td><td>Cost Price</td><td>السعر الذي اشتريت به المنتج من المورد — يستخدم لحساب تكلفة البضاعة المباعة (COGS)</td></tr>
                        <tr><td>سعر البيع</td><td>Selling Price</td><td>السعر الذي يدفعه العميل — يُعتمد في POS وفواتير المبيعات</td></tr>
                        <tr><td>الحد الأدنى</td><td>Min Quantity</td><td>عند نزول الكمية لهذا الحد يظهر تنبيه في لوحة التحكم وتقرير المخزون المنخفض</td></tr>
                        <tr><td>تاريخ الانتهاء</td><td>Expiry Date</td><td>يظهر في لوحة التحكم عند اقتراب الانتهاء بـ30 يوماً</td></tr>
                        <tr><td>الباركود</td><td>Barcode</td><td>يمكن مسحه مباشرة في POS لإضافة المنتج للسلة</td></tr>
                    </table>
                </div>

                <div class="alert-tip">
                    <i class="bi bi-arrow-repeat"></i>
                    الكمية تُحدَّث تلقائياً: تنقص عند البيع (+مراجعة عند تراجع المبيعة)، وتزيد عند تسجيل فاتورة شراء جديدة.
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════ --}}
        {{-- 5. Purchases --}}
        {{-- ══════════════════════════════════════════════════════════ --}}
        <div class="help-section" id="sec-purchases">
            <div class="section-header">
                <i class="bi bi-truck fs-5"></i> المشتريات والموردون
            </div>
            <div class="section-body">
                <p class="small text-muted">تسجيل فواتير الشراء يزيد المخزون ويُنشئ ذمة دائنة للمورد في حالة الشراء الآجل.</p>

                <h6 class="subsection-title"><i class="bi bi-file-earmark-text"></i> حالات الدفع</h6>
                <div class="table-responsive">
                    <table class="table table-sm small">
                        <tr><td><span class="badge bg-success">مدفوعة</span></td><td>تم سداد كامل الفاتورة عند التسجيل</td></tr>
                        <tr><td><span class="badge bg-warning text-dark">جزئية</span></td><td>دُفع جزء — الباقي يظهر في تقرير تقادم الموردين (AP Aging)</td></tr>
                        <tr><td><span class="badge bg-danger">غير مدفوعة</span></td><td>لم يُسدَّد شيء — تظهر بالكامل كذمة دائنة</td></tr>
                    </table>
                </div>

                <div class="gl-example">
                    <strong class="small">القيد عند شراء بضاعة (آجل):</strong>
                    <table class="mt-1">
                        <tr><td class="gl-debit">من: المخزون (1300)</td><td>← قيمة الشراء</td></tr>
                        <tr><td class="gl-credit">إلى: الموردون/ذمم دائنة (2000)</td><td>← ذمة على المورد</td></tr>
                    </table>
                </div>

                <h6 class="subsection-title mt-3"><i class="bi bi-cash-coin"></i> تسجيل دفعة لمورد</h6>
                <p class="small text-muted">
                    من قائمة الموردين → اختر المورد → "تسجيل دفعة". يمكن ربط الدفعة بفاتورة شراء محددة (يُحدَّث الرصيد المتبقي تلقائياً) أو تركها كدفعة عامة على حساب المورد.
                </p>
                <div class="gl-example">
                    <strong class="small">قيد سداد مورد:</strong>
                    <table class="mt-1">
                        <tr><td class="gl-debit">من: الموردون/ذمم دائنة (2000)</td><td>← تخفيض الذمة الدائنة</td></tr>
                        <tr><td class="gl-credit">إلى: الصندوق/النقدية (1000)</td><td>← خروج النقدية</td></tr>
                    </table>
                </div>
                <div class="alert-tip mt-2">
                    <i class="bi bi-lightbulb-fill"></i>
                    بعد تسجيل الدفعة تتغير حالة الفاتورة تلقائياً: "غير مدفوعة" → "جزئية" → "مدفوعة" حسب المبلغ المسدَّد.
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════ --}}
        {{-- 6. Customers --}}
        {{-- ══════════════════════════════════════════════════════════ --}}
        <div class="help-section" id="sec-customers">
            <div class="section-header">
                <i class="bi bi-people-fill fs-5"></i> العملاء والذمم المدينة (AR)
            </div>
            <div class="section-body">
                <h6 class="subsection-title"><i class="bi bi-person-plus"></i> إعداد العميل</h6>
                <p class="small text-muted">
                    عند إضافة عميل يمكن تحديد <strong>حد الائتمان</strong> — وهو أقصى مبلغ مسموح له بالشراء الآجل.
                    الصفر (0) يعني لا يوجد حد ائتماني. في POS لن يُسمح بإتمام بيع آجل إذا تجاوز الرصيد المديون حد الائتمان.
                </p>

                <h6 class="subsection-title"><i class="bi bi-cash"></i> تسجيل دفعة من العميل</h6>
                <p class="small text-muted">
                    من قائمة العملاء → اختر العميل → "تسجيل دفعة". يمكن ربط الدفعة بفاتورة معينة أو تركها عامة.
                    القيد التلقائي:
                </p>
                <div class="gl-example">
                    <table>
                        <tr><td class="gl-debit">من: الصندوق/النقدية (1000)</td><td>← المبلغ المقبوض</td></tr>
                        <tr><td class="gl-credit">إلى: الذمم المدينة (1200)</td><td>← سداد ذمة العميل</td></tr>
                    </table>
                </div>

                <h6 class="subsection-title"><i class="bi bi-clock-history"></i> تقرير تقادم الذمم (AR Aging)</h6>
                <p class="small text-muted">
                    يُصنِّف الفواتير المستحقة غير المسددة إلى أربع شرائح زمنية:
                </p>
                <div class="table-responsive">
                    <table class="table table-sm small">
                        <tr><td><span class="badge bg-primary">0–30 يوم</span></td><td>الجاري — ضمن فترة الائتمان الطبيعية</td></tr>
                        <tr><td><span class="badge bg-warning text-dark">31–60 يوم</span></td><td>تحتاج متابعة</td></tr>
                        <tr><td><span class="badge" style="background:#fd7e14;color:white">61–90 يوم</span></td><td>متأخرة — يُستحسن المطالبة الرسمية</td></tr>
                        <tr><td><span class="badge bg-danger">أكثر من 90 يوم</span></td><td>مشكوك في تحصيلها — قد تحتاج إجراءات قانونية</td></tr>
                    </table>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════ --}}
        {{-- 7. HR --}}
        {{-- ══════════════════════════════════════════════════════════ --}}
        <div class="help-section" id="sec-hr">
            <div class="section-header">
                <i class="bi bi-person-badge-fill fs-5"></i> الموارد البشرية والرواتب
            </div>
            <div class="section-body">
                <h6 class="subsection-title"><i class="bi bi-person-plus"></i> الموظفون</h6>
                <p class="small text-muted">كل موظف يُعرَّف بنوع العمل (دوام كامل / جزئي / عقد) ونوع الأجر (شهري / يومي / بالساعة) مع بنود الراتب التفصيلية.</p>
                <div class="table-responsive">
                    <table class="table table-sm term-table small">
                        <tr><td>الراتب الأساسي</td><td>Base Salary</td><td>القاعدة الرئيسية — يحتسب منها اليومي والساعي تلقائياً</td></tr>
                        <tr><td>بدل السكن</td><td>Housing Allowance</td><td>يُضاف لإجمالي المستحقات — لا يُخصم منه</td></tr>
                        <tr><td>بدل المواصلات</td><td>Transport Allowance</td><td>يُضاف لإجمالي المستحقات</td></tr>
                        <tr><td>بدلات أخرى</td><td>Other Allowances</td><td>أي بدل إضافي (مخصص وجبة، بدل عمل ليلي...)</td></tr>
                    </table>
                </div>

                <h6 class="subsection-title"><i class="bi bi-clock"></i> الورديات والحضور</h6>
                <p class="small text-muted">
                    عرِّف الورديات (صباحية/مسائية/ليلية) أولاً. ثم سجِّل الحضور يومياً من <strong>الحضور والانصراف ← اليومي</strong>.
                    يحتسب النظام ساعات العمل والأوفرتايم تلقائياً من وقت الدخول والخروج مقارنةً بساعات الوردية.
                </p>

                <div class="table-responsive">
                    <table class="table table-sm small">
                        <tr><td><span class="badge bg-success">حاضر</span></td><td>يوم عمل كامل</td></tr>
                        <tr><td><span class="badge bg-warning text-dark">نصف يوم</span></td><td>يُحتسب 0.5 يوم للراتب</td></tr>
                        <tr><td><span class="badge bg-danger">غائب</span></td><td>يُخصم منه يوم بسعر اليومي</td></tr>
                        <tr><td><span class="badge bg-info">إجازة</span></td><td>يوم مدفوع الأجر</td></tr>
                    </table>
                </div>

                <h6 class="subsection-title"><i class="bi bi-cash-stack"></i> مسير الرواتب — الخطوات</h6>
                <div class="workflow-step">
                    <div class="step-num">1</div>
                    <div class="step-body">
                        <div class="step-title">معاينة المسير</div>
                        <div class="step-desc">من "مسير الرواتب ← معاينة" — اختر الشهر والسنة. يعرض النظام احتساب كل موظف قبل الحفظ.</div>
                    </div>
                </div>
                <div class="workflow-step">
                    <div class="step-num">2</div>
                    <div class="step-body">
                        <div class="step-title">حفظ كمسودة</div>
                        <div class="step-desc">انقر "حفظ المسير" — يُنشئ مسيراً بحالة "مسودة" يمكن مراجعته بدون ترحيل محاسبي.</div>
                    </div>
                </div>
                <div class="workflow-step">
                    <div class="step-num">3</div>
                    <div class="step-body">
                        <div class="step-title">الاعتماد والترحيل</div>
                        <div class="step-desc">من صفحة المسير → "اعتماد وترحيل القيد" — يُنشئ قيد محاسبي: مصاريف رواتب ← نقدية + مستحقات.</div>
                    </div>
                </div>
                <div class="workflow-step">
                    <div class="step-num">4</div>
                    <div class="step-body">
                        <div class="step-title">تحميل القسائم</div>
                        <div class="step-desc">من جدول الموظفين في المسير، انقر زر PDF لكل موظف لتحميل قسيمة راتبه.</div>
                    </div>
                </div>

                <div class="gl-example mt-2">
                    <strong class="small">قيد الرواتب (عند الاعتماد):</strong>
                    <table class="mt-1">
                        <tr><td class="gl-debit">من: مصاريف الرواتب (6200)</td><td>← إجمالي المستحقات</td></tr>
                        <tr><td class="gl-credit">إلى: النقدية/الصندوق (1000)</td><td>← صافي الراتب المصروف</td></tr>
                        <tr><td class="gl-credit">إلى: رواتب مستحقة الدفع (2100)</td><td>← الاستقطاعات المحتجزة</td></tr>
                    </table>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════ --}}
        {{-- 8. Accounting --}}
        {{-- ══════════════════════════════════════════════════════════ --}}
        <div class="help-section" id="sec-accounting">
            <div class="section-header">
                <i class="bi bi-calculator-fill fs-5"></i> المحاسبة والقيود
            </div>
            <div class="section-body">
                <h6 class="subsection-title"><i class="bi bi-book"></i> القيد المزدوج (Double-Entry)</h6>
                <p class="small text-muted">
                    يعتمد النظام على <strong>مبدأ القيد المزدوج</strong>: كل عملية تُقيَّد في جانبين متساويين — مدين (Debit) ودائن (Credit).
                    الجانبان يجب أن يتساويا دائماً (ميزان المراجعة).
                </p>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="card border p-3 h-100">
                            <h6 class="fw-bold text-primary small"><i class="bi bi-arrow-up-circle"></i> ما يرفع الرصيد المدين (DR)</h6>
                            <ul class="small text-muted mb-0 ps-3">
                                <li>الأصول (نقدية، مخزون، ذمم مدينة)</li>
                                <li>المصاريف والتكاليف</li>
                                <li>الخسائر</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border p-3 h-100">
                            <h6 class="fw-bold text-success small"><i class="bi bi-arrow-down-circle"></i> ما يرفع الرصيد الدائن (CR)</h6>
                            <ul class="small text-muted mb-0 ps-3">
                                <li>الخصوم والالتزامات</li>
                                <li>حقوق الملكية</li>
                                <li>الإيرادات والأرباح</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <h6 class="subsection-title" id="sec-accounts"><i class="bi bi-list-columns-reverse"></i> شجرة الحسابات</h6>
                <p class="small text-muted">تُنظَّم الحسابات وفق التسلسل الرقمي التالي:</p>
                <div class="table-responsive">
                    <table class="table table-sm term-table small">
                        <tr><td>1000–1499</td><td>أصول متداولة</td><td>نقدية، بنك، مخزون، ذمم مدينة</td></tr>
                        <tr><td>1500–1999</td><td>أصول ثابتة</td><td>معدات، أثاث، عقارات</td></tr>
                        <tr><td>2000–2499</td><td>خصوم متداولة</td><td>موردون، رواتب مستحقة</td></tr>
                        <tr><td>2500–2999</td><td>خصوم طويلة الأجل</td><td>قروض</td></tr>
                        <tr><td>3000–3999</td><td>حقوق الملكية</td><td>رأس المال، الأرباح المحتجزة</td></tr>
                        <tr><td>4000–4999</td><td>الإيرادات</td><td>المبيعات، الإيرادات الأخرى</td></tr>
                        <tr><td>5000–5999</td><td>تكلفة البضاعة</td><td>COGS — تكلفة ما تم بيعه</td></tr>
                        <tr><td>6000+</td><td>مصاريف تشغيلية</td><td>رواتب، إيجارات، مرافق</td></tr>
                    </table>
                </div>

                <h6 class="subsection-title"><i class="bi bi-gear-fill"></i> ربط الحسابات بالعمليات التلقائية</h6>
                <p class="small text-muted">
                    يجب ربط أكواد الحسابات في <strong>إدارة النظام ← إعدادات النظام ← حسابات الإيرادات والتكاليف / الأصول / الالتزامات</strong>
                    قبل البدء بأي عملية. راجع <a href="#sec-settings">قسم إعدادات النظام</a> للتفاصيل الكاملة.
                </p>
                <div class="alert-tip">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    إذا لم تُربط الحسابات لن تُرحَّل أي قيود محاسبية تلقائية (مبيعات، مشتريات، رواتب).
                </div>

                <h6 class="subsection-title"><i class="bi bi-calendar-check"></i> إقفال نهاية السنة</h6>
                <p class="small text-muted">
                    في نهاية كل سنة مالية يجب إقفال حسابات الإيرادات والمصاريف وترحيل صافي الربح (أو الخسارة) إلى حساب <strong>الأرباح المحتجزة (3100)</strong>.
                    من محاسبة ← إقفال نهاية السنة.
                </p>
                <div class="workflow-step">
                    <div class="step-num">1</div>
                    <div class="step-body">
                        <div class="step-title">معاينة نتائج السنة</div>
                        <div class="step-desc">اختر السنة واضغط "معاينة" — يعرض إجمالي الإيرادات والمصاريف وصافي الربح، مع قائمة الحسابات التي سيتم إقفالها.</div>
                    </div>
                </div>
                <div class="workflow-step">
                    <div class="step-num">2</div>
                    <div class="step-body">
                        <div class="step-title">تنفيذ الإقفال</div>
                        <div class="step-desc">انقر "تنفيذ الإقفال السنوي" — يُنشئ النظام قيد يُصفِّر جميع حسابات الإيرادات والمصاريف ويُحوِّل الصافي إلى الأرباح المحتجزة.</div>
                    </div>
                </div>
                <div class="gl-example mt-1">
                    <strong class="small">قيد الإقفال (مثال — سنة مربحة):</strong>
                    <table class="mt-1">
                        <tr><td class="gl-debit">من: إيرادات المبيعات (4000)</td><td>← إقفال الإيراد</td></tr>
                        <tr><td class="gl-credit">إلى: تكلفة البضاعة المباعة (5000)</td><td>← إقفال COGS</td></tr>
                        <tr><td class="gl-credit">إلى: مصاريف تشغيلية (6xxx)</td><td>← إقفال المصاريف</td></tr>
                        <tr><td class="gl-credit">إلى: الأرباح المحتجزة (3100)</td><td>← صافي الربح</td></tr>
                    </table>
                </div>
                <div class="alert-warn mt-2">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <strong>تحذير:</strong> عملية الإقفال لا رجعة فيها. تأكد من مراجعة ميزان المراجعة وتدقيق جميع القيود قبل التنفيذ.
                    لا يمكن إقفال نفس السنة مرتين.
                </div>

                <h6 class="subsection-title"><i class="bi bi-bar-chart"></i> القوائم المالية</h6>
                <div class="row g-2">
                    <div class="col-md-4">
                        <div class="card border p-2 text-center h-100">
                            <div class="fw-bold small">قائمة الدخل</div>
                            <div class="text-muted" style="font-size:0.75rem">الإيرادات − التكاليف = صافي الربح</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border p-2 text-center h-100">
                            <div class="fw-bold small">الميزانية العمومية</div>
                            <div class="text-muted" style="font-size:0.75rem">الأصول = الخصوم + حقوق الملكية + صافي الربح</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border p-2 text-center h-100">
                            <div class="fw-bold small">ميزان المراجعة</div>
                            <div class="text-muted" style="font-size:0.75rem">يتحقق من توازن مجموع المدين = مجموع الدائن</div>
                        </div>
                    </div>
                </div>

                <h6 class="subsection-title"><i class="bi bi-arrow-counterclockwise"></i> قيود التصحيح (Reversals)</h6>
                <p class="small text-muted">
                    في حال الخطأ في قيد محاسبي، استخدم <strong>قيود التصحيح</strong> بدلاً من حذف القيد.
                    يُنشئ قيد عكسي يُلغي أثر القيد الأصلي ويُحافظ على مسار المراجعة.
                    هذه الميزة متاحة للمدير فقط.
                </p>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════ --}}
        {{-- 9. Reports --}}
        {{-- ══════════════════════════════════════════════════════════ --}}
        <div class="help-section" id="sec-reports">
            <div class="section-header">
                <i class="bi bi-graph-up-arrow fs-5"></i> التقارير
            </div>
            <div class="section-body">
                <div class="table-responsive">
                    <table class="table table-sm small">
                        <thead class="table-light"><tr><th>التقرير</th><th>ما يعرضه</th><th>الاستخدام</th></tr></thead>
                        <tbody>
                            <tr><td class="fw-bold">المبيعات</td><td>مبيعات يومية بالتاريخ والعدد والإجمالي</td><td>مراقبة الأداء اليومي والشهري</td></tr>
                            <tr><td class="fw-bold">المشتريات</td><td>فواتير الشراء خلال فترة زمنية</td><td>متابعة إجمالي الشراء والمتبقي</td></tr>
                            <tr><td class="fw-bold">الأرباح</td><td>الإيراد − التكلفة = ربح + نسبة هامش</td><td>تحليل ربحية الفترة</td></tr>
                            <tr><td class="fw-bold">المخزون</td><td>قيمة المخزون الحالية لكل منتج</td><td>تقييم الأصول الجارية</td></tr>
                            <tr><td class="fw-bold">أكثر مبيعاً</td><td>ترتيب المنتجات حسب الإيراد أو الكمية</td><td>قرارات التوريد والترويج</td></tr>
                            <tr><td class="fw-bold">تقادم ذمم العملاء</td><td>الفواتير الآجلة مصنَّفة زمنياً (AR Aging)</td><td>متابعة التحصيل</td></tr>
                            <tr><td class="fw-bold">تقادم ذمم الموردين</td><td>فواتير الشراء غير المسددة (AP Aging)</td><td>إدارة السيولة والمدفوعات</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════ --}}
        {{-- 10. System Settings --}}
        {{-- ══════════════════════════════════════════════════════════ --}}
        <div class="help-section" id="sec-settings">
            <div class="section-header">
                <i class="bi bi-gear-fill fs-5"></i> إعدادات النظام
            </div>
            <div class="section-body">
                <p class="small text-muted">
                    تُدار من <strong>إدارة النظام ← إعدادات النظام</strong> (يتطلب صلاحية <code>settings.view</code>).
                    تُحفظ في جدول <code>settings</code> وتُخزَّن في الذاكرة المؤقتة (Cache) ليوم واحد.
                    كل تغيير ينعكس فوراً على POS والفواتير والرواتب والقيود المحاسبية.
                </p>

                {{-- Store Identity --}}
                <h6 class="subsection-title"><i class="bi bi-shop"></i> هوية المتجر</h6>
                <div class="table-responsive">
                    <table class="table table-sm term-table small">
                        <tr><td>اسم المتجر</td><td><code>store_name</code></td><td>يظهر في رأس الإيصال الحراري وفاتورة PDF</td></tr>
                        <tr><td>العنوان</td><td><code>store_address</code></td><td>يظهر في رأس الإيصال</td></tr>
                        <tr><td>رقم الهاتف</td><td><code>store_phone</code></td><td>يظهر في رأس الإيصال</td></tr>
                        <tr><td>الرقم الضريبي</td><td><code>store_tax_number</code></td><td>يظهر في الإيصال (اتركه فارغاً إن لم يكن مطلوباً)</td></tr>
                    </table>
                </div>

                {{-- Currency & VAT --}}
                <h6 class="subsection-title"><i class="bi bi-currency-exchange"></i> العملة وضريبة القيمة المضافة</h6>
                <div class="table-responsive">
                    <table class="table table-sm term-table small">
                        <tr><td>رمز العملة</td><td><code>currency_symbol</code></td><td>يُعرَض بجانب كل مبلغ في POS والفواتير والتقارير — الافتراضي: <strong>ج.م</strong></td></tr>
                        <tr><td>تفعيل ضريبة القيمة المضافة</td><td><code>vat_enabled</code></td><td>تبديل عام — عند الإيقاف تُصبح قيمة الضريبة صفراً في كل العمليات</td></tr>
                        <tr><td>نسبة الضريبة (%)</td><td><code>vat_rate</code></td><td>مثال: 15 تعني 15% — يحتسبها الخادم (server-side) بغض النظر عن القيمة التي يُرسلها POS</td></tr>
                        <tr><td>الضريبة مدمجة</td><td><code>vat_inclusive</code></td><td><strong>مُدمجة (Inclusive):</strong> السعر يشمل الضريبة — يُستخرج مبلغها من الإجمالي. <strong>منفصلة (Exclusive):</strong> تُضاف على الإجمالي</td></tr>
                    </table>
                </div>
                <div class="alert-tip">
                    <i class="bi bi-shield-check"></i>
                    احتساب الضريبة يتم <strong>على الخادم</strong> (Server-authoritative) — قيمة الضريبة التي يُرسلها المتصفح تُتجاهَل ويُعاد احتسابها من الإعدادات لمنع التلاعب.
                </div>

                {{-- POS --}}
                <h6 class="subsection-title"><i class="bi bi-bag-check"></i> نقطة البيع والمبيعات</h6>
                <div class="table-responsive">
                    <table class="table table-sm term-table small">
                        <tr><td>أقصى نسبة خصم (%)</td><td><code>max_discount_percent</code></td><td>يمنع الكاشير من إدخال خصم يتجاوز هذه النسبة — يُطبَّق في POS وعلى الخادم</td></tr>
                        <tr><td>أقصى أيام ائتمان</td><td><code>max_credit_days</code></td><td>معلوماتي — يُعرض في إعدادات البيع الآجل</td></tr>
                        <tr><td>نص ذيل الفاتورة</td><td><code>receipt_footer</code></td><td>مثال: "شكراً لزيارتكم" — يظهر أسفل الإيصال الحراري</td></tr>
                        <tr><td>عرض الفاتورة</td><td><code>receipt_width_mm</code></td><td>58mm أو 80mm — يُحدد حجم الطباعة الحرارية</td></tr>
                        <tr><td>بيع المخزون السالب</td><td><code>allow_negative_stock</code></td><td>تفعيل: يُسمح بالبيع حتى لو الكمية صفر. إيقاف: بطاقات المنتجات الفارغة تُعطَّل في POS</td></tr>
                        <tr><td>تفعيل البيع الآجل</td><td><code>credit_sales_enabled</code></td><td>تحكم بظهور خيار "بيع آجل" في شاشة POS</td></tr>
                    </table>
                </div>

                {{-- Fiscal Year --}}
                <h6 class="subsection-title"><i class="bi bi-calendar-range"></i> السنة المالية</h6>
                <div class="table-responsive">
                    <table class="table table-sm term-table small">
                        <tr><td>شهر بداية السنة المالية</td><td><code>fiscal_year_start_month</code></td><td>الافتراضي: يناير (1). لتغيير بداية السنة إلى مثلاً أبريل اختر 4 — يؤثر على فترات التقارير المالية</td></tr>
                    </table>
                </div>

                {{-- HR --}}
                <h6 class="subsection-title"><i class="bi bi-people"></i> الموارد البشرية والرواتب</h6>
                <div class="table-responsive">
                    <table class="table table-sm term-table small">
                        <tr><td>أيام العمل في الشهر</td><td><code>working_days_per_month</code></td><td>الافتراضي: 26 — يُستخدم لحساب قيمة يوم العمل من الراتب الشهري: <code>راتب ÷ 26 × أيام الحضور</code></td></tr>
                        <tr><td>معامل الساعات الإضافية</td><td><code>overtime_rate_multiplier</code></td><td>الافتراضي: 1.5 — ساعة أوفرتايم = أجر ساعة عادية × 1.5</td></tr>
                        <tr><td>نسبة التأمينات — الموظف (%)</td><td><code>social_insurance_employee_pct</code></td><td>الافتراضي: 9% — تُخصم من إجمالي راتب الموظف وتُسجَّل في بند "استقطاعات أخرى" بمسير الرواتب</td></tr>
                        <tr><td>نسبة التأمينات — صاحب العمل (%)</td><td><code>social_insurance_employer_pct</code></td><td>الافتراضي: 21% — معلوماتي فقط (حصة صاحب العمل لا تظهر في مسير الرواتب حالياً)</td></tr>
                    </table>
                </div>

                {{-- Inventory --}}
                <h6 class="subsection-title"><i class="bi bi-box-seam"></i> المخزون والعملاء</h6>
                <div class="table-responsive">
                    <table class="table table-sm term-table small">
                        <tr><td>الحد الأدنى للمخزون الافتراضي</td><td><code>default_min_stock_quantity</code></td><td>القيمة الافتراضية لحقل "الحد الأدنى" عند إضافة منتج جديد</td></tr>
                        <tr><td>تنبيه انتهاء الصلاحية قبل (أيام)</td><td><code>expiry_alert_days</code></td><td>يُظهر تنبيهاً في لوحة التحكم للمنتجات التي ستنتهي خلال هذه الأيام — الافتراضي: 30</td></tr>
                        <tr><td>حد الائتمان الافتراضي للعميل</td><td><code>default_credit_limit</code></td><td>القيمة الافتراضية لحقل "حد الائتمان" عند إضافة عميل جديد. الصفر = بلا حد</td></tr>
                    </table>
                </div>

                {{-- GL Account Mappings --}}
                <h6 class="subsection-title"><i class="bi bi-graph-up-arrow"></i> ربط الحسابات المحاسبية (GL Mappings)</h6>
                <p class="small text-muted">هذه الإعدادات هي الأهم — تربط كل عملية تلقائية بحساب دفتر الأستاذ الصحيح:</p>
                <div class="table-responsive">
                    <table class="table table-sm small">
                        <thead class="table-light"><tr><th>الإعداد</th><th>المجموعة</th><th>الاستخدام</th></tr></thead>
                        <tbody>
                            <tr><td class="fw-bold">حساب المبيعات</td><td>إيرادات وتكاليف</td><td>يُقيَّد دائناً عند كل عملية بيع</td></tr>
                            <tr><td class="fw-bold">حساب تكلفة البضاعة المباعة (COGS)</td><td>إيرادات وتكاليف</td><td>يُقيَّد مديناً بتكلفة البضاعة المباعة</td></tr>
                            <tr><td class="fw-bold">حساب خصم المبيعات</td><td>إيرادات وتكاليف</td><td>يُقيَّد مديناً بمبلغ الخصم الممنوح للعميل</td></tr>
                            <tr><td class="fw-bold">حساب الصندوق (نقد)</td><td>أصول</td><td>يُقيَّد مديناً عند البيع النقدي أو استلام دفعة من عميل</td></tr>
                            <tr><td class="fw-bold">حساب البنك</td><td>أصول</td><td>يُقيَّد مديناً عند البيع بالبطاقة أو التحويل</td></tr>
                            <tr><td class="fw-bold">حساب العملاء (ذمم مدينة)</td><td>أصول</td><td>يُقيَّد مديناً عند البيع الآجل، دائناً عند استلام الدفعة</td></tr>
                            <tr><td class="fw-bold">حساب المخزون</td><td>أصول</td><td>يُقيَّد مديناً عند الشراء، دائناً عند البيع (قيد COGS)</td></tr>
                            <tr><td class="fw-bold">حساب ضريبة القيمة المضافة المدخلات</td><td>أصول</td><td>ضريبة الشراء (VAT المدفوعة للمورد)</td></tr>
                            <tr><td class="fw-bold">حساب الموردين (ذمم دائنة)</td><td>التزامات</td><td>يُقيَّد دائناً عند شراء آجل، مديناً عند السداد</td></tr>
                            <tr><td class="fw-bold">حساب ضريبة القيمة المضافة المستحقة</td><td>التزامات</td><td>يُقيَّد دائناً بضريبة المبيعات المستحقة للجهات الضريبية</td></tr>
                            <tr><td class="fw-bold">حساب الرواتب المستحقة الدفع</td><td>التزامات</td><td>يُقيَّد دائناً بالاستقطاعات من مسير الرواتب</td></tr>
                            <tr><td class="fw-bold">حساب مصروف الرواتب</td><td>مصاريف / حقوق ملكية</td><td>يُقيَّد مديناً بإجمالي مسير الرواتب</td></tr>
                            <tr><td class="fw-bold">حساب الأرباح المحتجزة</td><td>مصاريف / حقوق ملكية</td><td>يُرحَّل إليه صافي الربح/الخسارة عند إقفال السنة المالية</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="alert-warn">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <strong>تنبيه مهم:</strong> إذا تُرك أي حساب فارغاً (— لم يُحدَّد —) ستفشل العمليات التي تحتاجه في إنشاء القيود المحاسبية.
                    يُنصح بالتأكد من صحة كل الحسابات بعد إنشاء شجرة الحسابات مباشرةً.
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════ --}}
        {{-- 12. Glossary --}}
        {{-- ══════════════════════════════════════════════════════════ --}}
        <div class="help-section" id="sec-glossary">
            <div class="section-header">
                <i class="bi bi-translate fs-5"></i> قاموس المصطلحات
            </div>
            <div class="section-body">
                <div class="table-responsive">
                    <table class="table table-sm term-table small table-hover">
                        <thead class="table-dark"><tr><th>المصطلح عربي</th><th>الإنجليزي</th><th>التعريف</th></tr></thead>
                        <tbody>
                            <tr><td>دفتر الأستاذ</td><td>General Ledger (GL)</td><td>سجل كل القيود المحاسبية مرتبة بالحساب والتاريخ</td></tr>
                            <tr><td>قيد محاسبي</td><td>Journal Entry</td><td>تسجيل عملية مالية بجانبين: مدين ودائن</td></tr>
                            <tr><td>ميزان المراجعة</td><td>Trial Balance</td><td>قائمة بأرصدة كل الحسابات للتحقق من التوازن</td></tr>
                            <tr><td>الذمم المدينة</td><td>Accounts Receivable (AR)</td><td>مبالغ مستحقة على العملاء للمنشأة</td></tr>
                            <tr><td>الذمم الدائنة</td><td>Accounts Payable (AP)</td><td>مبالغ مستحقة من المنشأة للموردين</td></tr>
                            <tr><td>تكلفة البضاعة المباعة</td><td>COGS</td><td>التكلفة الفعلية للبضاعة التي تم بيعها</td></tr>
                            <tr><td>هامش الربح الإجمالي</td><td>Gross Margin</td><td>(المبيعات − COGS) ÷ المبيعات × 100</td></tr>
                            <tr><td>صافي الربح</td><td>Net Income</td><td>الإيرادات − التكاليف − المصاريف التشغيلية</td></tr>
                            <tr><td>التقادم</td><td>Aging</td><td>تصنيف الديون حسب عمرها الزمني</td></tr>
                            <tr><td>قيد عكسي/تصحيح</td><td>Reversal Entry</td><td>قيد يُلغي أثر قيد سابق خاطئ</td></tr>
                            <tr><td>مسير الرواتب</td><td>Payroll Run</td><td>احتساب رواتب موظفين الشهر وترحيل القيد</td></tr>
                            <tr><td>الوردية</td><td>Shift</td><td>جدول الدوام (صباحي/مسائي/ليلي) بوقت بداية ونهاية</td></tr>
                            <tr><td>الأوفرتايم</td><td>Overtime</td><td>ساعات عمل إضافية تُحتسب بمعدل 1.5× الساعي</td></tr>
                            <tr><td>قائمة الدخل</td><td>Income Statement</td><td>تقرير الإيرادات والمصاريف وصافي الربح لفترة</td></tr>
                            <tr><td>الميزانية العمومية</td><td>Balance Sheet</td><td>صورة لحظية للأصول والخصوم وحقوق الملكية</td></tr>
                            <tr><td>حد الائتمان</td><td>Credit Limit</td><td>أقصى مبلغ مسموح للعميل بالشراء الآجل</td></tr>
                            <tr><td>سجل المراجعة</td><td>Audit Log</td><td>تتبع كل العمليات — من فعلها ومتى وما الذي تغيّر</td></tr>
                            <tr><td>إقفال السنة</td><td>Year-End Closing</td><td>تصفير أرصدة الإيرادات والمصاريف وترحيل صافي الربح للأرباح المحتجزة</td></tr>
                            <tr><td>الأرباح المحتجزة</td><td>Retained Earnings</td><td>تراكم صافي الأرباح السنوية — يزيد بالأرباح وينقص بالخسائر والتوزيعات</td></tr>
                            <tr><td>ضريبة القيمة المضافة</td><td>VAT / Tax Payable</td><td>ضريبة على المبيعات تُسجَّل كالتزام دائن حتى يتم تسديدها للجهات الضريبية</td></tr>
                            <tr><td>حساب رأسي</td><td>Header Account</td><td>حساب تلخيصي لا تُرحَّل عليه قيود مباشرة — يجمع أرصدة الحسابات الفرعية</td></tr>
                            <tr><td>خصم المبيعات</td><td>Sales Discount</td><td>تخفيض ممنوح للعميل في POS — يُقيَّد مدين في حساب الخصم (4300)</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════ --}}
        {{-- 13. Common Workflows --}}
        {{-- ══════════════════════════════════════════════════════════ --}}
        <div class="help-section" id="sec-workflows">
            <div class="section-header">
                <i class="bi bi-diagram-3-fill fs-5"></i> سيناريوهات الاستخدام الشائعة
            </div>
            <div class="section-body">

                <h6 class="subsection-title"><i class="bi bi-1-circle"></i> إعداد المتجر للمرة الأولى</h6>
                <div class="workflow-step"><div class="step-num">1</div><div class="step-body"><div class="step-title">إنشاء شجرة الحسابات</div><div class="step-desc">محاسبة ← شجرة الحسابات ← استيراد CSV أو إضافة يدوية. استخدم التسلسل الرقمي الموضح أعلاه.</div></div></div>
                <div class="workflow-step"><div class="step-num">2</div><div class="step-body"><div class="step-title">ربط الحسابات في إعدادات النظام</div><div class="step-desc">إدارة النظام ← إعدادات النظام ← أقسام "حسابات الإيرادات / الأصول / الالتزامات" — تأكد من إدخال أكواد الحسابات الصحيحة (نقدية، مبيعات، مخزون...).</div></div></div>
                <div class="workflow-step"><div class="step-num">3</div><div class="step-body"><div class="step-title">إضافة الفئات والمنتجات</div><div class="step-desc">المبيعات والمخزون ← الفئات ← إضافة. ثم المنتجات ← إضافة منتج لكل صنف مع باركود وسعر تكلفة وبيع.</div></div></div>
                <div class="workflow-step"><div class="step-num">4</div><div class="step-body"><div class="step-title">إضافة الموردين والموظفين</div><div class="step-desc">المشتريات ← الموردون ← إضافة. ثم الموارد البشرية ← الموظفون ← الورديات.</div></div></div>
                <div class="workflow-step"><div class="step-num">5</div><div class="step-body"><div class="step-title">تسجيل رصيد المخزون الافتتاحي</div><div class="step-desc">سجِّل فاتورة شراء افتتاحية بالكميات الحالية لكل منتج لإنشاء القيد الافتتاحي في دفتر الأستاذ.</div></div></div>

                <h6 class="subsection-title mt-4"><i class="bi bi-2-circle"></i> دورة الشراء الشهرية</h6>
                <div class="workflow-step"><div class="step-num">1</div><div class="step-body"><div class="step-title">استلام بضاعة من مورد</div><div class="step-desc">المشتريات ← فواتير الشراء ← إضافة — اختر المورد وأضف الأصناف والكميات. حدد حالة الدفع.</div></div></div>
                <div class="workflow-step"><div class="step-num">2</div><div class="step-body"><div class="step-title">سداد للمورد لاحقاً</div><div class="step-desc">المشتريات ← الموردون ← اختر المورد ← "تسجيل دفعة". اختر الفاتورة المراد سدادها وأدخل المبلغ. سيُرحَّل القيد المحاسبي تلقائياً وتُحدَّث حالة الفاتورة.</div></div></div>
                <div class="workflow-step"><div class="step-num">3</div><div class="step-body"><div class="step-title">مراجعة AP Aging</div><div class="step-desc">التقارير ← تقادم ذمم الموردين — تتبع ما تأخر سداده.</div></div></div>

                <h6 class="subsection-title mt-4"><i class="bi bi-3-circle"></i> نهاية الشهر (Month-End Close)</h6>
                <div class="workflow-step"><div class="step-num">1</div><div class="step-body"><div class="step-title">مراجعة ميزان المراجعة</div><div class="step-desc">تأكد من أن إجمالي المدين = إجمالي الدائن. أي فرق يشير لقيد غير مكتمل.</div></div></div>
                <div class="workflow-step"><div class="step-num">2</div><div class="step-body"><div class="step-title">تسوية دفعات الموردين</div><div class="step-desc">الموردون ← تقرير AP Aging لمتابعة الفواتير المستحقة. ثم الموردون ← تسجيل دفعة لكل مبلغ مسدَّد.</div></div></div>
                <div class="workflow-step"><div class="step-num">3</div><div class="step-body"><div class="step-title">تشغيل مسير الرواتب</div><div class="step-desc">الموارد البشرية ← مسير الرواتب ← معاينة ← حفظ ← اعتماد (يُرحِّل قيد الرواتب).</div></div></div>
                <div class="workflow-step"><div class="step-num">4</div><div class="step-body"><div class="step-title">إصدار القوائم المالية</div><div class="step-desc">محاسبة ← قائمة الدخل (حدد من/إلى الشهر). ثم الميزانية العمومية. تحقق من توازن الأصول = الخصوم + حقوق الملكية.</div></div></div>
                <div class="workflow-step"><div class="step-num">5</div><div class="step-body"><div class="step-title">مراجعة التقارير</div><div class="step-desc">التقارير ← الأرباح لمقارنة الربح الفعلي مع التوقعات. AR/AP Aging لمتابعة الذمم.</div></div></div>

                <h6 class="subsection-title mt-4"><i class="bi bi-4-circle"></i> إقفال السنة المالية</h6>
                <div class="workflow-step"><div class="step-num">1</div><div class="step-body"><div class="step-title">مراجعة نهائية لميزان المراجعة</div><div class="step-desc">محاسبة ← ميزان المراجعة — تأكد من عدم وجود قيود معلقة أو غير متوازنة.</div></div></div>
                <div class="workflow-step"><div class="step-num">2</div><div class="step-body"><div class="step-title">تدقيق الذمم المدينة والدائنة</div><div class="step-desc">تقارير AR/AP Aging — تأكد من تسوية جميع الفواتير القابلة للسداد.</div></div></div>
                <div class="workflow-step"><div class="step-num">3</div><div class="step-body"><div class="step-title">تنفيذ إقفال السنة</div><div class="step-desc">محاسبة ← إقفال نهاية السنة — اختر السنة، راجع الأرقام، انقر "تنفيذ الإقفال". يُنشئ النظام قيد إقفال ويُصفِّر الإيرادات والمصاريف.</div></div></div>
                <div class="workflow-step"><div class="step-num">4</div><div class="step-body"><div class="step-title">إصدار الميزانية الختامية</div><div class="step-desc">محاسبة ← الميزانية العمومية في آخر يوم من السنة — تأكد من ظهور صافي الربح في حقوق الملكية.</div></div></div>

            </div>
        </div>


        {{-- ══════════════ قسم جهاز البصمة ZKTeco ══════════════ --}}
        <div class="help-section" id="sec-zkteco">
            <div class="section-header">
                <i class="bi bi-fingerprint"></i> ربط جهاز البصمة (ZKTeco)
            </div>
            <div class="section-body">

                <div class="alert alert-primary">
                    <i class="bi bi-info-circle-fill"></i>
                    <strong>نظرة عامة:</strong> النظام يدعم ربط أجهزة البصمة <strong>ZKTeco</strong> عبر الشبكة المحلية (LAN)
                    لاستيراد سجلات الحضور والانصراف تلقائياً دون إدخال يدوي.
                </div>

                <h6 class="subsection-title"><i class="bi bi-clipboard-check"></i> المتطلبات</h6>
                <ul class="help-list">
                    <li>جهاز ZKTeco يدعم الاتصال عبر الشبكة (UDP/TCP) على المنفذ <code>4370</code></li>
                    <li>الجهاز والسيرفر على <strong>نفس الشبكة المحلية</strong> (LAN)</li>
                    <li>معرفة <strong>IP الجهاز</strong> — من إعدادات الجهاز ← الشبكة</li>
                    <li>كود الموظف في النظام يتطابق مع رقم المستخدم في الجهاز</li>
                </ul>

                <h6 class="subsection-title mt-4"><i class="bi bi-plug-fill"></i> خطوات الربط</h6>
                <div class="workflow-step"><div class="step-num">1</div><div class="step-body"><div class="step-title">فتح صفحة المزامنة</div><div class="step-desc">الموارد البشرية ← الحضور والانصراف ← مزامنة ZKTeco</div></div></div>
                <div class="workflow-step"><div class="step-num">2</div><div class="step-body"><div class="step-title">ضبط الإعدادات — تغيير الوضع إلى <span class="badge bg-success">Real</span></div><div class="step-desc">اضغط <strong>"الإعدادات"</strong> ← غيّر الوضع من <code>Demo</code> إلى <code>Real</code> ← أدخل IP الجهاز (مثال: <code>192.168.1.200</code>) والمنفذ <code>4370</code> ← اضغط <strong>"حفظ الإعدادات"</strong>.</div></div></div>
                <div class="workflow-step"><div class="step-num">3</div><div class="step-body"><div class="step-title">معاينة البيانات</div><div class="step-desc">اضغط <strong>"معاينة"</strong> لرؤية سجلات الحضور الموجودة في الجهاز قبل استيرادها.</div></div></div>
                <div class="workflow-step"><div class="step-num">4</div><div class="step-body"><div class="step-title">تنفيذ المزامنة</div><div class="step-desc">اضغط <strong>"مزامنة الآن"</strong> — يقرأ النظام السجلات ويحفظها مع حساب ساعات العمل تلقائياً.</div></div></div>

                <h6 class="subsection-title mt-4"><i class="bi bi-laptop"></i> الوضع التجريبي (Demo)</h6>
                <p class="help-text">في حال عدم توفر جهاز فعلي، فعّل <strong>وضع Demo</strong> الذي يولّد بيانات حضور وهمية واقعية لآخر 7 أيام بنسبة حضور 85% — مفيد للتدريب واختبار النظام.</p>

                <h6 class="subsection-title mt-4"><i class="bi bi-people-fill"></i> تطابق الموظفين مع الجهاز</h6>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <strong>مهم:</strong> <strong>كود الموظف</strong> في النظام (مثال: <code>EMP-0001</code>) يجب أن يطابق <strong>رقم المستخدم</strong> في جهاز البصمة. يمكن مراجعته من: الموارد البشرية ← الموظفون ← تعديل الموظف.
                </div>

                <h6 class="subsection-title mt-4"><i class="bi bi-bug-fill"></i> استكشاف الأخطاء</h6>
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr><th>المشكلة</th><th>السبب</th><th>الحل</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>فشل الاتصال</td><td>IP خطأ أو شبكة مختلفة</td><td>تحقق من IP في إعدادات الجهاز ← الشبكة</td></tr>
                        <tr><td>موظف لا تُحفظ سجلاته</td><td>كود الموظف غير متطابق</td><td>عدّل كود الموظف في النظام ليطابق رقمه في الجهاز</td></tr>
                        <tr><td>منفذ خاطئ</td><td>بعض الأجهزة تختلف</td><td>الافتراضي <code>4370</code> — راجع إعدادات الشبكة في الجهاز</td></tr>
                        <tr><td>ساعات العمل = صفر</td><td>تُسجَّل الدخول فقط</td><td>تأكد من تسجيل الخروج، أو أدخله يدوياً من سجل الحضور</td></tr>
                    </tbody>
                </table>

            </div>
        </div>

    </div>{{-- end col-lg-9 --}}
</div>{{-- end row --}}
@endsection

@push('scripts')
<script>
    // Highlight TOC link on scroll
    const sections = document.querySelectorAll('.help-section');
    const tocLinks = document.querySelectorAll('.help-toc .nav-link');

    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                tocLinks.forEach(l => l.classList.remove('active'));
                const link = document.querySelector('.help-toc a[href="#' + entry.target.id + '"]');
                if (link) link.classList.add('active');
            }
        });
    }, { rootMargin: '-30% 0px -60% 0px' });

    sections.forEach(s => observer.observe(s));
</script>
@endpush
