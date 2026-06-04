<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ExpenseInvoiceController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleReturnController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AccountingController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerPaymentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\FinancialStatementController;
use App\Http\Controllers\SupplierPaymentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\AccountingPeriodController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\SalesQuotationController;
use App\Http\Controllers\FixedAssetCategoryController;
use App\Http\Controllers\PosTerminalController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\FixedAssetController;
use App\Http\Controllers\PriceListController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\YearEndClosingController;
use App\Http\Controllers\RoleController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/home', function () {
    return redirect()->route('dashboard');
})->middleware('auth')->name('home');

Auth::routes(['login' => false]);

// Login with rate limiting (5 per minute per IP)
Route::get('login',  [\App\Http\Controllers\Auth\LoginController::class, 'showLoginForm'])->name('login');
Route::post('login', [\App\Http\Controllers\Auth\LoginController::class, 'login'])->middleware('throttle:login');

Route::middleware(['auth'])->group(function () {

    // ── Dashboard & Help ──────────────────────────────────────────────────────
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/help', fn() => view('help.index'))->name('help');

    // ── Categories ────────────────────────────────────────────────────────────
    Route::resource('categories', CategoryController::class);

    // ── Suppliers ─────────────────────────────────────────────────────────────
    Route::resource('suppliers', SupplierController::class);
    Route::get('suppliers/{supplier}/payments/create',
        [SupplierPaymentController::class, 'create'])->name('supplier-payments.create');
    Route::post('suppliers/{supplier}/payments',
        [SupplierPaymentController::class, 'store'])->name('supplier-payments.store');

    // ── Products ──────────────────────────────────────────────────────────────
    Route::get('products/barcode/{barcode}',
        [ProductController::class, 'getByBarcode'])->name('products.barcode');
    Route::get('products/import-template',
        [ProductController::class, 'importTemplate'])->name('products.import-template');
    Route::post('products/import',
        [ProductController::class, 'import'])->name('products.import');
    Route::resource('products', ProductController::class);
    Route::get('products-low-stock',
        [ProductController::class, 'lowStock'])->name('products.low-stock');
    Route::get('products-expiring',
        [ProductController::class, 'expiring'])->name('products.expiring');
    Route::get('inventory/lot-expiry',
        [ProductController::class, 'lotExpiry'])->name('inventory.lot-expiry');

    // ── Inventory Adjustments & Sessions (Stocktaking) ───────────────────────
    Route::prefix('inventory')->name('inventory.')->group(function () {
        // Level 1: Manual adjustments
        Route::get('adjustments',               [App\Http\Controllers\InventoryAdjustmentController::class, 'index']) ->name('adjustments.index');
        Route::get('adjustments/create',        [App\Http\Controllers\InventoryAdjustmentController::class, 'create'])->name('adjustments.create');
        Route::get('adjustments/search',        [App\Http\Controllers\InventoryAdjustmentController::class, 'searchProducts'])->name('adjustments.search');
        Route::post('adjustments',              [App\Http\Controllers\InventoryAdjustmentController::class, 'store']) ->name('adjustments.store');
        Route::get('adjustments/{adjustment}',  [App\Http\Controllers\InventoryAdjustmentController::class, 'show'])  ->name('adjustments.show');
        // Level 2: Periodic stocktake sessions
        Route::get('sessions',                  [App\Http\Controllers\InventorySessionController::class, 'index'])   ->name('sessions.index');
        Route::get('sessions/create',           [App\Http\Controllers\InventorySessionController::class, 'create'])  ->name('sessions.create');
        Route::post('sessions',                 [App\Http\Controllers\InventorySessionController::class, 'store'])   ->name('sessions.store');
        Route::get('sessions/{session}',        [App\Http\Controllers\InventorySessionController::class, 'show'])    ->name('sessions.show');
        Route::patch('sessions/{session}/item', [App\Http\Controllers\InventorySessionController::class, 'updateItem'])->name('sessions.update-item');
        Route::post('sessions/{session}/scan',  [App\Http\Controllers\InventorySessionController::class, 'scanBarcode'])->name('sessions.scan');
        Route::post('sessions/{session}/approve',[App\Http\Controllers\InventorySessionController::class, 'approve']) ->name('sessions.approve');
        Route::delete('sessions/{session}',     [App\Http\Controllers\InventorySessionController::class, 'cancel'])  ->name('sessions.cancel');
    });

    // ── HR ────────────────────────────────────────────────────────────────────
    Route::prefix('hr')->name('hr.')->group(function () {
        Route::resource('employees', EmployeeController::class)->names([
            'index'  => 'employees.index',
            'create' => 'employees.create',
            'store'  => 'employees.store',
            'show'   => 'employees.show',
            'edit'   => 'employees.edit',
            'update' => 'employees.update',
        ]);
        Route::resource('shifts', ShiftController::class)->except(['show']);
        Route::get('attendance/daily',   [AttendanceController::class, 'daily'])    ->name('attendance.daily');
        Route::post('attendance/daily',  [AttendanceController::class, 'saveDaily'])->name('attendance.save-daily');
        Route::get('attendance/monthly', [AttendanceController::class, 'monthly'])  ->name('attendance.monthly');
        Route::get('attendance/sync',         [AttendanceController::class, 'syncIndex'])   ->name('attendance.sync');
        Route::post('attendance/sync/now',    [AttendanceController::class, 'syncNow'])     ->name('attendance.sync-now');
        Route::get('attendance/sync/preview', [AttendanceController::class, 'syncPreview'])->name('attendance.sync-preview');
        Route::post('attendance/sync/settings',[AttendanceController::class,'saveZkSettings'])->name('attendance.sync-settings');
        Route::get('payroll',            [PayrollController::class, 'index'])       ->name('payroll.index');
        Route::get('payroll/preview',    [PayrollController::class, 'preview'])     ->name('payroll.preview');
        Route::post('payroll',           [PayrollController::class, 'store'])       ->name('payroll.store');
        Route::get('payroll/{payrollRun}',           [PayrollController::class, 'show'])   ->name('payroll.show');
        Route::patch('payroll/{payrollRun}/approve', [PayrollController::class, 'approve'])->name('payroll.approve');
        Route::get('payroll/{payrollRun}/items/{item}/payslip',
            [PayrollController::class, 'payslip'])->name('payroll.payslip');

        // ── Employee Leaves (الإجازات) ───────────────────────────────────────
        Route::get('leaves',                    [\App\Http\Controllers\LeaveController::class, 'index'])  ->name('leaves.index');
        Route::get('leaves/create',             [\App\Http\Controllers\LeaveController::class, 'create']) ->name('leaves.create');
        Route::post('leaves',                   [\App\Http\Controllers\LeaveController::class, 'store'])  ->name('leaves.store');
        Route::get('leaves/{leave}',            [\App\Http\Controllers\LeaveController::class, 'show'])   ->name('leaves.show');
        Route::patch('leaves/{leave}/approve',  [\App\Http\Controllers\LeaveController::class, 'approve'])->name('leaves.approve');
        Route::patch('leaves/{leave}/reject',   [\App\Http\Controllers\LeaveController::class, 'reject']) ->name('leaves.reject');

        // ── EOSB Provisions (مخصصات نهاية الخدمة) ───────────────────────────
        Route::get('eosb',              [\App\Http\Controllers\EosbController::class, 'index'])  ->name('eosb.index');
        Route::get('eosb/preview',      [\App\Http\Controllers\EosbController::class, 'preview'])->name('eosb.preview');
        Route::post('eosb/post',        [\App\Http\Controllers\EosbController::class, 'post'])   ->name('eosb.post');

        // ── Employee Loans (سلف الموظفين) ────────────────────────────────
        Route::get('loans',              [\App\Http\Controllers\EmployeeLoanController::class, 'index']) ->name('loans.index');
        Route::get('loans/create',       [\App\Http\Controllers\EmployeeLoanController::class, 'create'])->name('loans.create');
        Route::post('loans',             [\App\Http\Controllers\EmployeeLoanController::class, 'store']) ->name('loans.store');
        Route::get('loans/{loan}',       [\App\Http\Controllers\EmployeeLoanController::class, 'show'])  ->name('loans.show');
        Route::patch('loans/{loan}/cancel', [\App\Http\Controllers\EmployeeLoanController::class, 'cancel'])->name('loans.cancel');
    });

    // ── Customers ─────────────────────────────────────────────────────────────
    Route::resource('customers', CustomerController::class);
    Route::get('customers/{customer}/payments/create',
        [CustomerPaymentController::class, 'create'])->name('customer-payments.create');
    Route::post('customers/{customer}/payments',
        [CustomerPaymentController::class, 'store'])->name('customer-payments.store');
    Route::get('customers/{customer}/deposits/create',
        [\App\Http\Controllers\CustomerDepositController::class, 'create'])->name('customer-deposits.create');
    Route::post('customers/{customer}/deposits',
        [\App\Http\Controllers\CustomerDepositController::class, 'store'])->name('customer-deposits.store');

    // ── Purchases ─────────────────────────────────────────────────────────────
    Route::get('purchases/products/search',       [PurchaseController::class, 'searchProducts'])     ->name('purchases.products.search');
    Route::post('purchases/products/quick-create', [PurchaseController::class, 'quickCreateProduct'])->name('purchases.products.quick-create');
    Route::resource('purchases', PurchaseController::class)->except(['edit', 'update']);
    Route::get('purchases/{purchase}/pdf',
        [PurchaseController::class, 'pdf'])->name('purchases.pdf');

    // ── Purchase Orders (أوامر الشراء) ───────────────────────────────────────
    Route::resource('purchase-orders', PurchaseOrderController::class)
        ->only(['index', 'create', 'store', 'show']);
    Route::post('purchase-orders/{purchaseOrder}/send',
        [PurchaseOrderController::class, 'send'])->name('purchase-orders.send');
    Route::post('purchase-orders/{purchaseOrder}/cancel',
        [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');
    Route::get('purchase-orders/{purchaseOrder}/convert',
        [PurchaseOrderController::class, 'convertForm'])->name('purchase-orders.convert-form');
    Route::post('purchase-orders/{purchaseOrder}/convert',
        [PurchaseOrderController::class, 'convert'])->name('purchase-orders.convert');

    // ── Purchase Returns ──────────────────────────────────────────────────────
    Route::resource('purchase-returns', PurchaseReturnController::class)
        ->only(['index', 'create', 'store', 'show']);

    // ── Expense Invoices (فواتير المصروفات) ──────────────────────────────────
    Route::resource('expense-invoices', ExpenseInvoiceController::class)
        ->only(['index', 'create', 'store', 'show']);
    Route::get('expense-invoices/{expenseInvoice}/pay',
        [ExpenseInvoiceController::class, 'payForm'])->name('expense-invoices.pay-form');
    Route::post('expense-invoices/{expenseInvoice}/pay',
        [ExpenseInvoiceController::class, 'pay'])->name('expense-invoices.pay');

    // ── Vouchers (سندات القبض والصرف) ────────────────────────────────────────
    Route::prefix('vouchers')->name('vouchers.')->middleware('throttle:vouchers')->group(function () {
        Route::get('receipts/data',    [\App\Http\Controllers\ReceiptVoucherController::class, 'data'])->name('receipts.data')->withoutMiddleware('throttle:vouchers');
        Route::resource('receipts', \App\Http\Controllers\ReceiptVoucherController::class)->except(['edit', 'update']);
        Route::get('receipts/{receipt}/pdf', [\App\Http\Controllers\ReceiptVoucherController::class, 'pdf'])->name('receipts.pdf')->withoutMiddleware('throttle:vouchers');
        Route::get('payments/data',    [\App\Http\Controllers\PaymentVoucherController::class, 'data'])->name('payments.data')->withoutMiddleware('throttle:vouchers');
        Route::resource('payments', \App\Http\Controllers\PaymentVoucherController::class)->except(['edit', 'update']);
        Route::get('payments/{payment}/pdf', [\App\Http\Controllers\PaymentVoucherController::class, 'pdf'])->name('payments.pdf')->withoutMiddleware('throttle:vouchers');
    });

    // ── POS ───────────────────────────────────────────────────────────────────
    Route::get('/offline', fn() => view('pos.offline'))->name('pos.offline');
    Route::get('/pos',              [PosController::class, 'index'])  ->name('pos.index');
    Route::post('/pos',             [PosController::class, 'store'])  ->name('pos.store')->middleware('throttle:pos');
    Route::get('/pos/receipt/{id}', [PosController::class, 'receipt'])->name('pos.receipt');
    Route::get('/pos/customers/search', [PosController::class, 'searchCustomers'])->name('pos.customers.search');

    // ── Cash Shifts (وردية الكاشير) ───────────────────────────────────────────
    Route::prefix('pos/shifts')->name('pos.shifts.')->group(function () {
        Route::get('/',                  [\App\Http\Controllers\CashShiftController::class, 'index'])      ->name('index');
        Route::get('/open',              [\App\Http\Controllers\CashShiftController::class, 'createOpen']) ->name('open');
        Route::post('/open',             [\App\Http\Controllers\CashShiftController::class, 'storeOpen'])  ->name('store-open');
        Route::get('/{shift}',           [\App\Http\Controllers\CashShiftController::class, 'show'])       ->name('show');
        Route::get('/{shift}/close',     [\App\Http\Controllers\CashShiftController::class, 'createClose'])->name('close');
        Route::post('/{shift}/close',    [\App\Http\Controllers\CashShiftController::class, 'storeClose']) ->name('store-close');
    });

    // ── Sales ─────────────────────────────────────────────────────────────────
    Route::resource('sales', SaleController::class)->only(['index', 'show']);
    Route::get('sales/{sale}/pdf',    [SaleController::class, 'pdf'])    ->name('sales.pdf');
    Route::delete('sales/{sale}',     [SaleController::class, 'destroy'])->name('sales.destroy');

    // ── Sales Quotations (عروض الأسعار) ──────────────────────────────────────
    Route::get('sales-quotations/product-price',
        [SalesQuotationController::class, 'productPrice'])->name('sales-quotations.product-price');
    Route::resource('sales-quotations', SalesQuotationController::class)
        ->only(['index', 'create', 'store', 'show']);
    Route::post('sales-quotations/{salesQuotation}/send',
        [SalesQuotationController::class, 'send'])->name('sales-quotations.send');
    Route::post('sales-quotations/{salesQuotation}/reject',
        [SalesQuotationController::class, 'reject'])->name('sales-quotations.reject');
    Route::get('sales-quotations/{salesQuotation}/convert-to-order',
        [SalesQuotationController::class, 'convertToOrderForm'])->name('sales-quotations.convert-to-order-form');
    Route::post('sales-quotations/{salesQuotation}/convert-to-order',
        [SalesQuotationController::class, 'convertToOrder'])->name('sales-quotations.convert-to-order');

    // ── Sales Orders (أوامر البيع) ───────────────────────────────────────────
    Route::resource('sales-orders', SalesOrderController::class)->only(['index', 'show']);
    Route::post('sales-orders/{salesOrder}/confirm',
        [SalesOrderController::class, 'confirm'])->name('sales-orders.confirm');
    Route::post('sales-orders/{salesOrder}/cancel',
        [SalesOrderController::class, 'cancel'])->name('sales-orders.cancel');
    Route::get('sales-orders/{salesOrder}/convert',
        [SalesOrderController::class, 'convertToInvoiceForm'])->name('sales-orders.convert-form');
    Route::post('sales-orders/{salesOrder}/convert',
        [SalesOrderController::class, 'convertToInvoice'])->name('sales-orders.convert');

    // ── Sale Returns ──────────────────────────────────────────────────────────
    Route::resource('sale-returns', SaleReturnController::class)->only(['index', 'create', 'store', 'show']);

    // ── Reports ───────────────────────────────────────────────────────────────
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/',             [ReportController::class, 'index'])      ->name('index');
        Route::get('/sales',        [ReportController::class, 'sales'])      ->name('sales');
        Route::get('/purchases',    [ReportController::class, 'purchases'])  ->name('purchases');
        Route::get('/profit',       [ReportController::class, 'profit'])     ->name('profit');
        Route::get('/inventory',    [ReportController::class, 'inventory'])  ->name('inventory');
        Route::get('/top-products', [ReportController::class, 'topProducts'])->name('top-products');
        Route::get('/ar-aging',     [ReportController::class, 'arAging'])   ->name('ar-aging');
        Route::get('/ap-aging',     [ReportController::class, 'apAging'])   ->name('ap-aging');
    });

    // ── Accounting ────────────────────────────────────────────────────────────
    Route::get('/accounting', [AccountingController::class, 'index'])->name('accounting.index');

    Route::prefix('accounting')->name('accounting.')->group(function () {
        Route::get('/ledger',
            [App\Http\Controllers\LedgerController::class, 'index'])->name('ledger.index');
        Route::get('/ledger/{account}',
            [App\Http\Controllers\LedgerController::class, 'show'])->name('ledger.show');
        Route::get('/trial-balance',
            [App\Http\Controllers\TrialBalanceController::class, 'index'])->name('trial-balance');
        Route::get('/income-statement',
            [FinancialStatementController::class, 'incomeStatement'])->name('income-statement');
        Route::get('/balance-sheet',
            [FinancialStatementController::class, 'balanceSheet'])->name('balance-sheet');
        Route::get('/year-end-closing',
            [YearEndClosingController::class, 'index'])->name('year-end-closing');
        Route::post('/year-end-closing',
            [YearEndClosingController::class, 'store'])->name('year-end-closing.store');

        // ── Accounting Periods (Period Locking) ──────────────────────────────
        Route::get('/accounting-periods',
            [AccountingPeriodController::class, 'index'])->name('periods.index');
        Route::post('/accounting-periods/lock',
            [AccountingPeriodController::class, 'lock'])->name('periods.lock');
        Route::post('/accounting-periods/unlock',
            [AccountingPeriodController::class, 'unlock'])->name('periods.unlock');
    });

    // ── Users ─────────────────────────────────────────────────────────────────
    Route::resource('users', UserController::class);

    // ── Roles & Permissions (RBAC) ────────────────────────────────────────────
    Route::resource('roles', RoleController::class);
    Route::get('roles/{role}/permissions-of',
        [RoleController::class, 'permissionsOf'])->name('roles.permissions-of');

    // ── Chart of Accounts ─────────────────────────────────────────────────────
    Route::resource('accounts', App\Http\Controllers\AccountController::class);
    Route::get('accounts-import',
        [App\Http\Controllers\AccountController::class, 'importForm'])->name('accounts.import.form');
    Route::post('accounts-import',
        [App\Http\Controllers\AccountController::class, 'importCsv'])->name('accounts.import');
    Route::get('accounts-export',
        [App\Http\Controllers\AccountController::class, 'exportCsv'])->name('accounts.export');

    // ── Fixed Assets (الأصول الثابتة) ────────────────────────────────────────
    Route::resource('fixed-asset-categories', FixedAssetCategoryController::class)
        ->except(['show', 'destroy']);
    Route::resource('fixed-assets', FixedAssetController::class)
        ->only(['index', 'create', 'store', 'show']);
    Route::get('fixed-assets/{fixedAsset}/depreciate',
        [FixedAssetController::class, 'depreciateForm'])->name('fixed-assets.depreciate-form');
    Route::post('fixed-assets/{fixedAsset}/depreciate',
        [FixedAssetController::class, 'depreciate'])->name('fixed-assets.depreciate');
    Route::post('fixed-assets/depreciate-all',
        [FixedAssetController::class, 'depreciateAll'])->name('fixed-assets.depreciate-all');
    Route::get('fixed-assets/{fixedAsset}/dispose',
        [FixedAssetController::class, 'disposeForm'])->name('fixed-assets.dispose-form');
    Route::post('fixed-assets/{fixedAsset}/dispose',
        [FixedAssetController::class, 'dispose'])->name('fixed-assets.dispose');

    // ── POS Terminals ────────────────────────────────────────────────────────
    Route::resource('pos-terminals', PosTerminalController::class)->except(['show']);

    // ── Stock Transfers (تحويلات المخزون الداخلية) ──────────────────────────
    Route::resource('stock-transfers', StockTransferController::class)
        ->only(['index', 'create', 'store', 'show']);
    Route::post('stock-transfers/{stockTransfer}/complete',
        [StockTransferController::class, 'complete'])->name('stock-transfers.complete');
    Route::post('stock-transfers/{stockTransfer}/cancel',
        [StockTransferController::class, 'cancel'])->name('stock-transfers.cancel');
    Route::get('stock-transfers/level',
        [StockTransferController::class, 'stockLevel'])->name('stock-transfers.level');

    // ── Branches & Warehouses ────────────────────────────────────────────────
    Route::resource('branches', BranchController::class)->except(['show']);
    // ── Price Lists (Wholesale / Retail) ─────────────────────────────────────
    Route::resource('price-lists', PriceListController::class)->except(['show']);
    Route::match(['get','post'], 'price-lists/{priceList}/products',
        [PriceListController::class, 'products'])->name('price-lists.products');
    // ── POS: resolve prices for a customer/price-list ───────────────────────
    Route::post('pos/resolve-prices', [PosController::class, 'resolvePrices'])->name('pos.resolve-prices');
    Route::resource('warehouses', WarehouseController::class)->except(['show']);
    Route::get('warehouses/{warehouse}/stock',
        [WarehouseController::class, 'stock'])->name('warehouses.stock');

    // ── Settings ──────────────────────────────────────────────────────────────
    Route::get('settings/accounting',
        [App\Http\Controllers\SettingController::class, 'edit'])->name('settings.edit');
    Route::post('settings/accounting',
        [App\Http\Controllers\SettingController::class, 'update'])->name('settings.update');

    // ── Audit Logs ────────────────────────────────────────────────────────────
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit.logs.index');

    // ── Session keep-alive (called by JS idle timer) ──────────────────────────
    Route::post('session/ping', fn() => response()->json(['ok' => true]))->name('session.ping')
         ->middleware('throttle:session-ping');

    // ── Opening Balance Wizard ────────────────────────────────────────────────
    Route::get('accounting/opening-balance',       [\App\Http\Controllers\OpeningBalanceController::class, 'index']) ->name('accounting.opening-balance');
    Route::post('accounting/opening-balance',      [\App\Http\Controllers\OpeningBalanceController::class, 'store']) ->name('accounting.opening-balance.store');

    // ── Budgets (الموازنات) ───────────────────────────────────────────────────
    Route::resource('budgets', \App\Http\Controllers\BudgetController::class)
         ->only(['index','create','store','show']);
    Route::get('budgets/{budget}/variance', [\App\Http\Controllers\BudgetController::class, 'variance'])
         ->name('budgets.variance');

    // ── Cost Centers (مراكز التكلفة) ─────────────────────────────────────────
    Route::resource('cost-centers', \App\Http\Controllers\CostCenterController::class)
         ->only(['index','create','store','edit','update']);
    Route::get('cost-centers-report', [\App\Http\Controllers\CostCenterController::class, 'report'])
         ->name('cost-centers.report');

    // ── Consolidated P&L (all branches side by side) ──────────────────────────
    Route::get('accounting/consolidated-pl', [\App\Http\Controllers\ConsolidatedReportController::class, 'incomeStatement'])->name('accounting.consolidated-pl');

    // ── Inter-Branch Transfers (تحويلات بينية) ───────────────────────────────
    Route::prefix('inter-branch')->name('inter-branch.')->group(function () {
        Route::get('/',        [\App\Http\Controllers\InterBranchTransferController::class, 'index']) ->name('index');
        Route::get('/create',  [\App\Http\Controllers\InterBranchTransferController::class, 'create'])->name('create');
        Route::post('/',       [\App\Http\Controllers\InterBranchTransferController::class, 'store']) ->name('store');
        Route::get('/{interBranch}',[\App\Http\Controllers\InterBranchTransferController::class,'show'])->name('show');
    });

    // ── Backup Management ─────────────────────────────────────────────────────
    Route::get('backup',                    [\App\Http\Controllers\BackupController::class, 'index'])   ->name('backup.index');
    Route::post('backup/run',               [\App\Http\Controllers\BackupController::class, 'run'])     ->name('backup.run');
    Route::get('backup/download/{filename}',[\App\Http\Controllers\BackupController::class, 'download'])->name('backup.download');
    Route::delete('backup/{filename}',      [\App\Http\Controllers\BackupController::class, 'destroy']) ->name('backup.destroy');

    // ── Reversals ─────────────────────────────────────────────────────────────
    Route::get('reversals/create',
        [App\Http\Controllers\ReversalController::class, 'create'])->name('reversals.create');
    Route::get('reversals',
        [App\Http\Controllers\ReversalController::class, 'index'])->name('reversals.index');
    Route::get('reversals/{id}',
        [App\Http\Controllers\ReversalController::class, 'show'])->name('reversals.show');
    Route::post('reversals',
        [App\Http\Controllers\ReversalController::class, 'store'])->name('reversals.store');

    // ── Journal Entries ───────────────────────────────────────────────────────
    Route::resource('journal-entries', App\Http\Controllers\JournalEntryController::class)
        ->only(['index', 'create', 'store', 'show', 'destroy'])
        ->names('journal_entries');
    Route::get('journal-entries/{journal_entry}/pdf',
        [App\Http\Controllers\JournalEntryController::class, 'pdf'])->name('journal_entries.pdf');

    // ── Permissions (legacy redirect → new roles UI) ──────────────────────────
    Route::get('permissions',
        fn() => redirect()->route('roles.index'))->name('permissions.index');
    Route::post('permissions/toggle/{user}',
        [App\Http\Controllers\PermissionController::class, 'toggle'])->name('permissions.toggle');
});
