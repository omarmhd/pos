# CLAUDE.md — Supermarket POS / Mini ERP

## Project Overview

Laravel 12 mini-ERP targeting Arabic-speaking retail/restaurant businesses.
Arabic-first UI (RTL), Bootstrap 5 RTL, jQuery + DataTables, Yajra DataTables server-side, DomPDF for PDFs.

**Stack:** PHP 8.2 / Laravel 12 / MySQL / Blade / Bootstrap 5 RTL / jQuery

---

## MANDATORY RULE: Feature → Manual Update

**Every time a new feature, module, screen, or significant behavior change is implemented, you MUST update the user manual.**

The manual is located at:
```
resources/views/help/index.blade.php
```

### What counts as a "feature" that requires manual update
- Any new page or route accessible from the sidebar
- Any new form or workflow (create/edit/delete flow)
- Any new accounting document type (vouchers, entries, etc.)
- Any new POS capability (payment methods, fractional quantities, etc.)
- Any new customer/supplier feature
- Any new setting key that users need to configure
- Any new permission or role behavior

### How to update the manual

1. **Add a TOC entry** in the `<nav class="nav flex-column">` block (lines ~70-90):
   ```blade
   <a class="nav-link" href="#sec-YOURFEATURE">Feature Name</a>
   ```

2. **Add a content section** using the existing structure:
   ```blade
   <div class="help-section" id="sec-YOURFEATURE">
       <div class="section-header">
           <i class="bi bi-ICON fs-5"></i> Section Title
       </div>
       <div class="section-body">
           {{-- content --}}
       </div>
   </div>
   ```

3. **Use the correct CSS helpers** already defined in the file:
   - `.subsection-title` — sub-heading inside a section
   - `.workflow-step` + `.step-num` + `.step-title` + `.step-desc` — numbered steps
   - `.gl-example` — accounting journal entry examples
   - `.alert-tip` — blue tip box
   - `.alert-warn` — yellow warning box
   - `.term-table` — terminology tables

4. **Add accounting entries** for financial features using the GL example format:
   ```blade
   <div class="gl-example">
       <table class="w-100">
           <tr>
               <td class="gl-debit">مدين: حساب X</td>
               <td>المبلغ</td>
           </tr>
           <tr>
               <td class="gl-credit">دائن: حساب Y</td>
               <td>المبلغ</td>
           </tr>
       </table>
   </div>
   ```

### Sections that already exist (do not duplicate)
- `#sec-overview` — System overview
- `#sec-roles` — Roles and permissions
- `#sec-pos` — POS (Point of Sale)
- `#sec-inventory` — Inventory & products
- `#sec-purchases` — Purchases & suppliers
- `#sec-customers` — Customers & receivables
- `#sec-hr` — HR & payroll
- `#sec-zkteco` — Fingerprint device integration
- `#sec-accounting` — Accounting & journal entries
- `#sec-reports` — Reports
- `#sec-settings` — System settings
- `#sec-glossary` — Glossary
- `#sec-workflows` — Usage scenarios

### Features already implemented but NOT yet in the manual
The following features were added after the initial manual was written and need documentation:

- **سندات القبض والصرف** (Receipt & Payment Vouchers) — `/vouchers/receipts`, `/vouchers/payments`
- **إيداع رصيد العملاء** (Customer Deposit Balance) — customer page + POS deposit payment
- **الكميات الكسرية في نقطة البيع** (Fractional quantities in POS) — weight-based products
- **البحث السريع في فواتير الشراء** (Product search in purchase invoices) — Select2 AJAX search + quick-create
- **طريقة دفع رصيد الإيداع** (deposit_balance payment method in POS)

---

## Code Conventions

### Controllers
- All controllers use `$this->middleware('can:permission.name')` in constructor
- Resource controllers follow standard Laravel resource naming
- DataTables: use `DataTables::eloquent($query)` for server-side; for merged collections use dedicated `data()` method returning `response()->json(['data' => $rows])`
- Always call `->select('table.*')` BEFORE `->withSum()` / `->withCount()` to avoid overriding aggregate selects

### Models
- All currency/decimal fields cast as `'decimal:2'`
- Auto-generated document numbers use the boot pattern: create with `PENDING-{uniqid}`, update in `created()` hook
- Voucher number format: `PREFIX-Ymd-0001` (e.g., `RV-20260601-0001`)

### Blade Views
- All views extend `layouts.app`
- Currency always from `Setting::get('currency_symbol', 'ج.م')` — never hardcoded
- Flash messages use session keys: `success`, `error`, `warning`

### Ledger Posting
- All financial transactions post to GL via `LedgerPostingService`
- `->select()` must come before `->withSum()` in Eloquent queries
- When mapping Eloquent collections to plain arrays, always call `.toBase()` after `.map()` to avoid `getKey()` errors on plain arrays

### Permissions
- Add new permissions to `config/permission_groups.php` (for UI matrix)
- Add to `database/seeders/PermissionSeeder.php` for relevant roles
- Re-run `php artisan db:seed --class=PermissionSeeder` after changes

### Database
- Migrations use `DB::statement('ALTER TABLE ...')` for MySQL-specific column type changes
- Account 2050 = Customer Deposits (سُلَف العملاء) — current liability
- Setting key `account_customer_deposits_code` defaults to `'2050'`

---

## Key File Locations

| Purpose | Path |
|---------|------|
| User manual | `resources/views/help/index.blade.php` |
| Ledger posting logic | `app/Services/LedgerPostingService.php` |
| Permission groups | `config/permission_groups.php` |
| Permission seeder | `database/seeders/PermissionSeeder.php` |
| Sidebar navigation | `resources/views/layouts/app.blade.php` |
| POS view | `resources/views/pos/index.blade.php` |
| System settings | `app/Models/Setting.php` |

---

## Before Finishing Any Task

1. ✅ Did you update `resources/views/help/index.blade.php`?
2. ✅ Did you add the route to `routes/web.php`?
3. ✅ Did you add permissions to `config/permission_groups.php` and the seeder?
4. ✅ Did you add the sidebar link in `layouts/app.blade.php`?
5. ✅ Did you run `php artisan migrate` if there's a new migration?
6. ✅ Did you pass `$currency` from `Setting::get()` to every view that shows money?
