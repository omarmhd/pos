# Comprehensive ERP Audit Report — Supermarket POS / Mini-ERP

**Analysis Date:** 2026-06-12
**System:** Laravel 12 Mini-ERP (POS + Accounting + Inventory + HR) — Multi-Branch
**Methodology:** Architectural analysis + actual code-traced data flows (code-grounded audit), no generic theoretical assumptions.

> Methodology note: every finding below is grounded in actual reads of the codebase (controllers / services / models / migrations). Any item not confirmed by code is explicitly flagged as "needs further verification."

---

## 1. System Architecture Understanding

### 1.1 System Type
**Modular Monolith** (Laravel 12 / MySQL / Blade server-rendered) — a single application, single database, no external microservices or APIs. All modules share the same ORM and transactions (`DB::transaction`), which simplifies accounting consistency (DB-level ACID) but also means any lapse in discipline inside a single controller directly impacts the general ledger.

### 1.2 Modules — built on 65 Models

| Module | Core Models |
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

### 1.3 Multi-Branch Isolation
- All core financial tables (`journal_entries`, `sales`, `purchases`, `vouchers`, `payroll_runs`, ...) carry `branch_id` (migration `2026_06_02_180759_add_branch_id_to_financial_tables`).
- Isolation is **not enforced via Global Scope** but via a manual helper `effectiveBranchId()` in `app/Http/Controllers/Controller.php:42`, called explicitly in each controller.
- Permission logic (`GLOBAL_ROLES = ['admin','manager','reversal_manager']`): these roles **always see all branches**, unrestricted, even if assigned to a specific branch.
- When a new branch is created, dedicated cash/bank accounts are auto-created for it (`BranchAccountingService::setupAccounts()`).

### 1.4 Data Sources & External Integrations
- **ZKTeco** (`app/Services/ZKTecoService.php`): biometric fingerprint integration over IP/proprietary protocol — auto-syncs check-in/out to `Attendance`.
- **mPDF** (`PdfService.php`): RTL/Arabic PDF generation for invoices and reports.
- **None present**: no direct banking integration, no tax/e-invoicing (ETA) integration, no external e-commerce API. This is important for section 8 (Integration Testing) — most classic ERP integration scenarios **do not apply** to this system today.

---

## 2. Enterprise Data Flow Analysis

### 2.1 Order-to-Cash (O2C)
**Path:** POS (`PosController::store`) → `Sale` + `SaleItem[]` → `WarehouseService::out()` → `LedgerPostingService::postSale()` → Reports.

| Step | Detail |
|---|---|
| Input | POS cart (products, quantities, payment method: cash/bank/AR/deposit balance) |
| Process | `DB::transaction` (PosController.php:192-309) → `Sale::create` → `SaleItem::create` (stores `cost_price` snapshot at time of sale) → `WarehouseService::out()` (with `lockForUpdate`) |
| Posting | `postSale()` (LedgerPostingService.php:156-279):<br>• Debit: Cash/Bank (cash sale) or AR-1200 (credit sale) or Customer Deposits 2050 (deposit-balance payment)<br>• Debit: Sales Discount 4300 (if discount applied)<br>• Credit: Sales Revenue 4000 (= subtotal)<br>• Credit: Tax Payable 2200 (if tax applies)<br>• COGS entry: Debit COGS 5000 / Credit Inventory 1300 = Σ(qty × cost_price recorded at time of sale) |
| Output | `sale.is_posted = true`, the entry is reflected in Trial Balance / Income Statement immediately (same transaction) |

**AVCO note:** The `cost_price` used for COGS is the product's **point-in-time** value at the moment of sale (snapshot on `SaleItem`), updated by AVCO from the most recent purchase. This is accounting-correct (IAS 2).

### 2.2 Procure-to-Pay (P2P)
**Path:** `PurchaseController::store` → `Purchase` + `PurchaseItem[]` → AVCO (in `PurchaseItem::boot()`) → `WarehouseService::in()` → `LedgerPostingService::postPurchase()`.

| Step | Detail |
|---|---|
| Process | `DB::transaction` (PurchaseController.php:137-177). On `PurchaseItem::created()` (PurchaseItem.php:32-92): `AVCO = (oldQty×oldCost + newQty×newCost) / (oldQty+newQty)` is computed **before** `WarehouseService::in()` runs — important because `product->quantity` is still the old total at this point. A `CostPriceHistory` record is logged (method='avco') along with a `StockMovement` (including lot_number/expiry_date). |
| Posting | `postPurchase()` (LedgerPostingService.php:431-490):<br>• Debit: Inventory 1300 = total_amount<br>• Credit: Accounts Payable AP-2000 (unpaid portion)<br>• Credit: Cash 1000 (paid portion) |
| Later supplier payment | `postSupplierPayment()`: Debit AP-2000 / Credit Cash or Bank |

### 2.3 Record-to-Report (R2R)
- Every journal entry (`JournalEntry` + `JournalEntryLine[]`) flows through a single gateway: `LedgerPostingService::buildEntry()` (LPS.php:114-141).
- **Trial Balance** (`TrialBalanceController.php:30-76`): `SUM(debit)/SUM(credit)` per account up to `as_of_date`, filtered by `branch_id`, applying normal-balance rules (debit-normal for assets/expenses, credit-normal for liabilities/equity/revenue), and checking `|ΣDebit − ΣCredit| < 0.01`.
- **Year-End Closing** (`postYearEndClosing()` LPS.php:1266-1362): closes revenue and expense accounts into Retained Earnings 3100 in batches.

### 2.4 Inventory-to-Accounting (I2A)

| Operation | Inventory Impact | Ledger Impact |
|---|---|---|
| **Inventory Adjustment** | `WarehouseService::in/out` | Shortage → Debit 6510 (Inventory Shortage) / Credit 1300; Surplus → Debit 1300 / Credit 4100 (Inventory Surplus) |
| **Sales Return** | reverses inventory decrement | Debit Sales Returns 4200 / Credit Cash or AR; + Debit Inventory 1300 / Credit COGS 5000 |
| **Purchase Return** | decrements inventory | Debit Cash/AP / Credit Inventory 1300 |
| **Stock Transfer (between warehouses)** | `out()` then `in()` | **No accounting entry** (by design — explicitly stated in StockTransferController.php:151) |
| **Inter-Branch Transfer** | inventory movement + accounting | Two separate entries (from/to) using "Due-From"/"Due-To" inter-branch accounts (`postInterBranchTransfer` LPS.php:1182-1243) |

---

## 3. Accounting Controls

### 3.1 Double-Entry Validation
- **Single central checkpoint**: `buildEntry()` — every `post*()` call flows through it. Check: `abs($debits - $credits) > 0.005` ⇒ `RuntimeException("Entry is unbalanced")`.
- ✅ A genuine strength: 18+ different posting methods, all funnel through one gateway.

### 3.2 Period Locking
- `PeriodLockService::assertOpen($entryDate)` is called **inside** `buildEntry()` (LPS.php:118) — any new entry dated within a locked period (`AccountingPeriod.status='locked'`) is rejected immediately.
- Period lock is unique per (year, month), with support for locking a full year (`lockYear`).

### 3.3 Trial Balance Consistency
- Computed directly from `journal_entry_lines` (no separate cache that could drift) → consistency by construction.

### 3.4 Audit Trail
- `AuditLog` model (user_id, auditable_type/id, action, old/new values JSON, ip).
- Coverage: **not comprehensive** — observers are active on Sale, Purchase, Account, Reversal, and Settings only. Other changes (e.g., editing a purchase invoice after posting, or deleting a voucher) may not be recorded in AuditLog (needs further verification per-controller).

### 3.5 Duplicate Posting Prevention
- DB-level: `journal_entries.entry_number` UNIQUE + composite UNIQUE `(source_type, source_id, reference)`.
- App-level: `is_posted` flags on Sale/Purchase prevent re-posting.
- Entry number format is auto-generated after insert (`JournalEntry::boot()`, PENDING→JE-YYYYMMDD-NNNNNN pattern), consistent with CLAUDE.md conventions.

### 3.6 ⚠️ Critical Gap: Payroll Posting Bypasses the Central Posting Gateway
**Location:** `app/Http/Controllers/PayrollController.php:198-210`

When a payroll run is approved, `JournalEntry::create([...])` and `JournalEntryLine::create([...])` are called **directly**, NOT via `LedgerPostingService::postX()` / `buildEntry()`.

**Impact:**
1. **No `PeriodLockService::assertOpen()` check** — a payroll run can be approved and posted for a date within a **locked** accounting period, whereas every other operation (sales/purchases/vouchers) would be rejected. This is a direct control inconsistency (posting-rule enforcement is not uniform across modules).
2. **No `branch_id`** is set on the `JournalEntry::create` call — the entry is saved with `branch_id = NULL`. Since Trial Balance/financial statements filter by `je.branch_id = selected_branch`, **the salary expense will not appear in any branch-level financial statement**, only in consolidated reports (if those don't apply the same filter) — a likely "Salary Expense" mismatch between a branch report and the consolidated report.
3. A manual balance check (`abs($debits-$credits) > 0.005`) does exist (lines 194-196) — good, but it's **duplicated logic** instead of reusing `buildEntry()`, violating the "single posting gateway" principle from section 3.1.

**Classification:** Critical — Finance-impacting, affects R2R and period-close controls.
**Recommendation:** Add a `LedgerPostingService::postPayrollRun()` method that builds the same lines but routes through `buildEntry()` (passing `branch_id` from `$payrollRun->branch_id` or the employee's branch).

### 3.7 ⚠️ Default Account-Code Collision (4150 / 6520)
From the `Setting` table:
- `account_asset_gain_code` = 4150 **and** `account_pos_cash_overage_code` = 4150 (same code)
- `account_asset_loss_code` = 6520 **and** `account_pos_cash_shortage_code` = 6520 (same code)

**Impact:** If the client doesn't override these defaults, "POS cash-shift overages" and "gains on fixed-asset disposal" will post to the **same GL account** — and similarly "cash-shift shortages" and "losses on fixed-asset disposal." This makes financial analysis harder (no separation of recurring operational items from rare extraordinary items) and hides trends in recurring shift-cash variances inside an "extraordinary gains/losses" line.
**Classification:** Medium (configuration risk, not a code defect) — needs a warning during initial setup, or distinct default codes.

---

## 4. Security & Roles Analysis (RBAC / SoD)

### 4.1 Structure
- Spatie/Permission, 26 permission groups (~100 permissions), 5 seeded roles: Admin, Manager, Cashier, Accountant, Reversal Manager.
- Clear separation between `*.create`, `*.post`, and `*.reverse` (e.g., `sales.create` ≠ `sales.post` ≠ `sales.reverse`) — theoretically supports Segregation of Duties.

### 4.2 ⚠️ Potential Privilege-Escalation Scenario: "Manager" as a Global Role
`Controller::GLOBAL_ROLES = ['admin', 'manager', 'reversal_manager']` (Controller.php:27).

Per the code comment: *"Admin & Manager: Company-level authority → see everything"*. However, in a multi-branch system, a "Branch Manager" is typically a **branch-scoped** role, not a company-wide one. If the seeding/onboarding process creates multiple users with role "Manager" — one per branch (e.g., Cairo branch manager, Alexandria branch manager) — then **each of them can view and modify data for every other branch**, due to `GLOBAL_ROLES`. This contradicts the "Multi-branch isolation" claimed in section 1.3.

**Classification:** High (Security/SoD) — **needs confirmation**: is the "Manager" role intended for a single company-wide general manager (acceptable), or used as a template for multiple per-branch managers (a gap)? Recommendation: introduce a separate `branch_manager` role, NOT included in `GLOBAL_ROLES`, for branch-level managers, leaving "Manager" reserved for senior/HQ management.

### 4.3 Approval Workflows
- `sales.post` / `purchases.post` / `accounting.post` are separate from `*.create` — in principle this allows separating "who creates the document" from "who posts it to the ledger", **however** `postSale()` is called automatically inside the same transaction at the moment a POS sale is created (PosController.php:307) — i.e., **there is effectively no separate approval step for POS sales**; the `sales.post` permission may have no practical effect on the normal POS flow (needs verification: is it used elsewhere, e.g., a separate manual SaleController distinct from PosController?).
- Reversal: restricted to a whitelist of source types (Sale, Purchase, ReceiptVoucher, PaymentVoucher, PayrollRun, InventoryAdjustment) with a "prevent double reversal" guard — good.

### 4.4 Multi-Branch Isolation — Assessment
| Case | Behavior | Assessment |
|---|---|---|
| Admin/Manager/Reversal Manager | sees all branches, optional filter | ✅ but see 4.2 |
| Employee assigned to a branch (cashier/accountant) | locked to their branch, cannot change | ✅ |
| Employee with no branch (general accountant) | sees all, optional filter | ✅ intended |
| **Manual journal entries (`/journal-entries`)** | Not verified: is `branch_id` forced automatically based on the current user, or can a branch accountant submit an entry tagged with another branch's `branch_id`? | **Needs verification** — if not enforced, this is a data-isolation breach |

---

## 5. QA Strategy — Focused on Discovered Risks

Given the Modular Monolith nature with no external APIs, the classic test strategy is adapted as follows:

| Test Type | Priority | Focus |
|---|---|---|
| **Unit Testing** | High | `LedgerPostingService::buildEntry()` (balance check), `PeriodLockService::assertOpen()`, AVCO calculation in `PurchaseItem::boot()` |
| **Financial Reconciliation** | Critical | `stock_levels` vs `products.quantity` reconciliation, COGS-to-AVCO matching, per-branch Trial Balance vs consolidated report (especially after fixing 3.6) |
| **Integration Testing** | Low | Limited — only ZKTeco sync (no real banking/tax APIs) |
| **System/E2E Testing** | Critical | Full Purchase→Sale→Return→Payment→Closing cycle (section 7) |
| **Regression** | High | Any change to `LedgerPostingService` or `WarehouseService` must re-run the full E2E cycle, since every module depends on them |
| **Security/SoD** | Critical | Tests for 4.2 and 4.4 above |
| **Data Migration** | N/A | No external data-migration process currently exists |

---

## 6. Detailed Test Cases (Core Financial Cycle)

| ID | Module | Scenario | Preconditions | Steps | Expected Result | Accounting Impact (Dr/Cr) | Priority |
|---|---|---|---|---|---|---|---|
| TC-O2C-01 | POS | Normal cash sale | Product with sufficient stock, open period | Create a cash sale: product × qty × price | `sale.is_posted=true`, balanced JE, stock_levels−=qty | Dr 1000 (Cash)=Total; Dr 5000 (COGS)=qty×cost; Cr 4000 (Sales)=subtotal; Cr 2200 (Tax) if any; Cr 1300 (Inventory)=qty×cost | Critical |
| TC-O2C-02 | POS | Credit sale | Customer has AR account | Sale without immediate cash payment, `is_credit=true` | Customer AR balance increases by total | Dr 1200 (AR)=Total; Cr 4000; + COGS entry as above | Critical |
| TC-O2C-03 | POS | Payment via deposit balance | Customer has sufficient deposit balance ≥ amount | Select `deposit_balance` as payment method | Customer deposit balance decreases | Dr 2050 (Customer Deposits)=balance_used; Cr 4000 | High |
| TC-O2C-04 | POS | Sale with discount | — | Enter a discount % / amount | Discount posted to 4300 | Dr 4300 (Sales Discount)=discount; rest of entry as usual | Medium |
| TC-O2C-05 | Sales Return | Cash sales return | Previously posted sale invoice | Create a return for all/part of the invoice | Inventory increases, COGS reversed | Dr 4200 (Sales Returns); Cr 1000/1200; + Dr 1300 / Cr 5000 (cost reversal) | High |
| TC-P2P-01 | Purchases | Purchase invoice, partial payment | Supplier exists, open period | Purchase qty @ unit price, pay part in cash | Product `cost_price` updated via AVCO, new `CostPriceHistory` row method='avco' | Dr 1300 (Inventory)=total; Cr 2000 (AP)=unpaid; Cr 1000 (Cash)=paid | Critical |
| TC-P2P-02 | Purchases | AVCO across two consecutive purchases | Existing stock qty₁@cost₁ | Purchase qty₂@cost₂ | `new_cost = (qty₁×cost₁+qty₂×cost₂)/(qty₁+qty₂)` to 4 decimal places | Numeric verification only (no direct entry — COGS impact later) | Critical |
| TC-P2P-03 | Suppliers | Supplier payment | Purchase invoice with outstanding AP | Record partial/full payment | AP balance decreases | Dr 2000 (AP)=amount; Cr 1000/1100 | High |
| TC-P2P-04 | Purchase Return | Purchase return | Previously posted purchase invoice | Create a return | Inventory decreases | Dr 1000/2000 (cash refund or AP reduction); Cr 1300 | Medium |
| TC-I2A-01 | Inventory Adjustment | Inventory shortage | Physical count < system count | Record adjustment for shortage | `journal_entry_id` set on the Adjustment | Dr 6510 (Inventory Shortage); Cr 1300 | High |
| TC-I2A-02 | Inventory Adjustment | Inventory surplus | Physical count > system count | Record adjustment for surplus | — | Dr 1300; Cr 4100 (Inventory Surplus) | High |
| TC-I2A-03 | Stock Transfer | Transfer between two warehouses, same branch | — | Transfer a quantity | No accounting entry is created (by design) — verify **no** JournalEntry is generated | No GL impact | Medium |
| TC-I2A-04 | Inter-Branch Transfer | Transfer between two branches | Both branches have Due-From/Due-To accounts | Transfer goods from Branch A to Branch B | Two separate entries (A and B) | Branch A: Dr Due-From-B / Cr Inventory-A; Branch B: Dr Inventory-B / Cr Due-To-A | High |
| TC-R2R-01 | Period Lock | Block posting in a locked period | Lock a specific month via `/accounting-periods` | Attempt a sale/purchase dated within the locked period | **Operation rejected** with "Accounting period is locked" message | No entry created | Critical |
| TC-R2R-02 | **Payroll vs Period Lock** ⚠️ | Approve a payroll run dated within a locked period | Lock the month, create a PayrollRun dated within it | Approve the run | **Currently expected: posting is allowed (gap 3.6)** — should be rejected like every other module | Dr 6200 (Salaries) / Cr 1000 + 2100... | Critical |
| TC-R2R-03 | **Payroll Branch Filter** ⚠️ | Following TC-R2R-02 | Open Trial Balance for a specific branch vs the consolidated report | Check whether the payroll entry appears | **Expected: entry with branch_id=NULL doesn't appear in the branch-level report** — mismatch vs consolidated report | Exposes the 3.6 gap | Critical |
| TC-R2R-04 | Trial Balance | Overall balance | After any set of operations | Open Trial Balance | `ΣDebit = ΣCredit` across the whole report | `isBalanced=true` | Critical |
| TC-R2R-05 | Reversal | Reverse a posted sales invoice | Posted sale, `sales.reverse` permission | Create a Reversal | New entry with reference `REV-{original}`, debit/credit swapped, original marked `is_reversed=true` | Full reversal of the TC-O2C-01 entry | High |
| TC-R2R-06 | Reversal | Prevent double reversal | Invoice already reversed (TC-R2R-05) | Attempt to reverse it again | Rejected — "already reversed" | No new entry | High |
| TC-SEC-01 | RBAC | Cashier user tries to access Trial Balance | User with Cashier role only | Open `/accounting/trial-balance` | 403 Forbidden | — | Critical |
| TC-SEC-02 | Branch Isolation | Branch A accountant tries to view Branch B's data | User `branch_id=A`, not a global role | Change `branch_id` in query string to request Branch B | Only Branch A data returned (requested filter is ignored) | — | Critical |
| TC-SEC-03 | RBAC | "Manager" role assigned to a specific branch | User role=manager, branch_id=A | Attempt to access Branch B's reports/sales | **Currently expected: allowed (GLOBAL_ROLES) — needs confirmation whether intended (see 4.2)** | — | High |
| TC-HR-01 | Payroll | Employee loan deduction within payroll run | Employee has an active `EmployeeLoan`, `remaining_balance>0` | Approve a run including the loan installment | `remaining_balance` decreases FIFO, `status='settled'` once it reaches zero | Cr 1250 (Employee Loans)=loan_deduction (within the payroll entry) | Medium |
| TC-CFG-01 | Settings | Account-code collision 4150/6520 | New install, default settings | Record a shift overage/shortage + a fixed-asset sale with gain/loss | Verify whether both values land in the **same** GL account or different accounts | Exposes gap 3.7 | Medium |

---

## 7. End-to-End Full Financial Cycle Simulation

**Scenario:** `Purchase goods on credit → Sell part of it for cash → Partial sales return → Pay the supplier → Close the cash shift → Verify in financial statements`

1. **Purchase 100 units @ EGP 10 each** (fully on AP):
   - Dr 1300 (Inventory) = 1000 / Cr 2000 (AP) = 1000
   - `product.cost_price` updated via AVCO → if prior balance was zero, `cost_price = 10`
2. **Sell 30 units @ EGP 15 each, cash** (no discount/tax):
   - Dr 1000 (Cash) = 450 / Cr 4000 (Sales) = 450
   - Dr 5000 (COGS) = 300 (30×10) / Cr 1300 (Inventory) = 300
   - `stock_levels.quantity` = 70
3. **Return 10 units from the above sale (cash refund)**:
   - Dr 4200 (Sales Returns) = 150 / Cr 1000 (Cash) = 150
   - Dr 1300 (Inventory) = 100 (10×10) / Cr 5000 (COGS) = 100
   - `stock_levels.quantity` = 80
4. **Pay supplier EGP 1000**:
   - Dr 2000 (AP) = 1000 / Cr 1000 (Cash) = 1000 → AP balance = 0
5. **Close the cash shift** (reconcile actual cash vs expected):
   - Expected net cash = 450 (sale) − 150 (return) − 1000 (supplier payment) = −700 (a net cash outflow — illustrative)
   - Any variance (shortage/overage) → 6520 or 4150 (note the collision in 3.7)

**Reconciliation Checks:**
| Item | Expected Value |
|---|---|
| `products.quantity` after all steps | 80 |
| `Σstock_levels.quantity` across all warehouses for this product | = 80 (matches products.quantity) |
| Trial Balance — account 1300 (Inventory) | 1000 (purchase) − 300 (COGS on sale) + 100 (COGS reversal on return) = **800** = 80 units × EGP 10 ✅ |
| Trial Balance — account 4000 (Sales) net | 450 (revenue) — the return is NOT subtracted from 4000, it's posted to 4200 (Returns) — **the Income Statement must compute `Net Sales = 4000 − 4200`** — needs confirmation that `FinancialStatementService` applies this subtraction |
| Cash (1000) net movement | +450 − 150 − 1000 = −700 |
| `isBalanced` on every entry | true |

**Potential Failure Points (must be tested against a live environment):**
- Does `FinancialStatementService` compute "Net Sales" by subtracting 4200 from 4000, or does it display them as two separate line items with no explicit subtraction? (determines Income Statement accuracy)
- Does the reversed COGS for the return use the `cost_price` **at the time of the original sale** (stored on SaleItem) or the product's **current** `cost_price` (which may have changed due to a new AVCO-affecting purchase between the sale and the return)? — if the current price is used, step 3's entry above would be inaccurate.

---

## 8. Integration Testing

| Integration | Status | Notes |
|---|---|---|
| ERP ↔ Banking | **Not present** | No banking APIs. Any "bank account" is purely an internal GL account (1100). |
| ERP ↔ Tax/E-invoicing | **Not present** | `account_tax_payable_code` exists but is purely an accounting code, no integration with a tax authority. |
| ERP ↔ POS Hardware | Partial | No direct ESC/POS integration found in `app/Services` — receipts are PDF via mPDF. If thermal printing happens via JS/browser (outside Laravel), it's out of scope for this backend analysis. |
| ERP ↔ Fingerprint (ZKTeco) | ✅ Present | `ZKTecoService` polls the device over IP (default 192.168.1.100:4370?) — **needs confirmation that this address is configurable via Settings rather than hardcoded**, otherwise it's an operational risk across multiple branches/devices. |
| ERP ↔ Inventory (internal) | ✅ | Consistent via `WarehouseService` with `lockForUpdate`. |

**Concurrency Risk:** `WarehouseService::out()` uses `lockForUpdate` — good, prevents race conditions when the same product is sold from multiple POS terminals simultaneously. **Not verified**: do `PurchaseItem::boot()` (AVCO) and `WarehouseService::in()` operate under the same row lock to prevent AVCO conflicts when two purchases of the same product are received concurrently?

---

## 9. Financial Integrity Review

| Criterion | Assessment | Basis |
|---|---|---|
| **Entry Accuracy (Double Entry)** | ✅ Strong | Unified `buildEntry()` gateway with strict balance check (≤0.005) |
| **Trial Balance Consistency** | ✅ Structurally strong, ⚠️ with caveat | Computed directly from journal_entry_lines; **however** the payroll entry (3.6) can produce discrepancies between a branch report and the consolidated report |
| **Period Control** | ⚠️ Inconsistent | Enforced for all operations except payroll (3.6) |
| **Duplicate/Double-Posting Prevention** | ✅ Strong | UNIQUE constraints + `is_posted` + `is_reversed` |
| **Branch Isolation** | ⚠️ Needs confirmation | Sound logic for regular roles, but "Manager" as a global role could break isolation if used as a template for branch managers (4.2) |
| **Cost Tracking (AVCO)** | ✅ Logically sound | Formula correct, timing correct (before quantity update), historical logging present (CostPriceHistory) |
| **Overall Risk Level** | **Medium-High** | Risks are concentrated in two specific points (payroll posting gap + Manager global role) rather than spread throughout — relatively easy to remediate |

---

## 10. Final Audit Report & Recommendations

### Strengths
1. A single, central GL-posting gateway (`buildEntry`) with balance validation and period locking — a stronger design than many custom-built systems.
2. AVCO is correctly implemented with proper timing and full historical logging (`CostPriceHistory`).
3. Multi-layered duplicate-posting protection (DB constraints + application flags).
4. A disciplined Reversal mechanism with a type whitelist + double-reversal guard.

### Critical, Finance-Impacting Bugs
| # | Issue | Location | Impact |
|---|---|---|---|
| 1 | Payroll posting bypasses `buildEntry()` — no period-lock check, and `branch_id=NULL` | `PayrollController.php:198-210` | Allows posting salaries into a locked accounting period; hides salary expense from branch-level financial statements |
| 2 | "Manager" role as a Global Role could break branch isolation if assigned to multiple branch managers | `Controller.php:27` | Potential cross-branch data isolation breach |

### Configuration Risks
| # | Issue | Impact |
|---|---|---|
| 3 | Default account-code collision: 4150 (POS shift overage = asset disposal gain) and 6520 (POS shift shortage = asset disposal loss) | Mixes recurring operational items with extraordinary items in financial statements |

### Open Items (Not Resolved by Code Review Alone)
4. Do manual journal entries (`/journal-entries`) automatically enforce the current user's `branch_id`, or can it be spoofed?
5. Does `FinancialStatementService` subtract 4200 (Sales Returns) from 4000 when computing "Net Sales" on the Income Statement?
6. On a sales return, is the reversed COGS based on the `cost_price` recorded at the time of the **original sale** (snapshot) or the product's **current** `cost_price`?
7. Is the ZKTeco device IP address configurable via Settings or hardcoded?
8. Does AuditLog coverage include edits/deletions of posted documents (Sale/Purchase after `is_posted=true`)?

### Recommended Improvement Plan (Priority Order)
1. **(Critical, immediate)** Add `LedgerPostingService::postPayrollRun()` routed through `buildEntry()` with a correct `branch_id` — resolves both parts of gap #1.
2. **(High)** Audit all users with the "Manager" role: if multiple users hold this role across different branches, create a separate `branch_manager` role excluded from `GLOBAL_ROLES`.
3. **(Medium)** Separate the default account codes: `account_pos_cash_overage_code`/`account_pos_cash_shortage_code` should differ from `account_asset_gain_code`/`account_asset_loss_code` (e.g., 4160/6530).
4. **(Medium)** Implement TC-R2R-02, TC-R2R-03, TC-SEC-03, and TC-CFG-01 from section 6 as automated Laravel Feature Tests to prevent regressions.
5. **(Low)** Investigate and close open items 4-8 above, documenting resolutions in this report.

---

*This report was produced via direct analysis of the source code (Controllers/Services/Models/Migrations) without relying on generic theoretical assumptions. Any item marked "needs further verification" requires an executable test case against a live environment to confirm.*
