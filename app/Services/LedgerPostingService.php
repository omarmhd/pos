<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\CustomerDeposit;
use App\Models\EmployeeLoan;
use App\Models\PaymentVoucher;
use App\Models\PayrollRun;
use App\Models\Purchase;
use App\Models\AssetDepreciationEntry;
use App\Models\ExpenseInvoice;
use App\Models\FixedAsset;
use App\Models\ExpensePayment;
use App\Models\PurchaseReturn;
use App\Models\ReceiptVoucher;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Setting;
use App\Models\SupplierPayment;
use Carbon\Carbon;
use RuntimeException;

class LedgerPostingService
{
    /** Per-request account code cache to avoid repeated lookups */
    private array $cache = [];

    // ── internal helpers ─────────────────────────────────────────────────────

    /**
     * Get cash account ID for a branch (falls back to generic 1000.00).
     * Called from every post*() method that involves cash.
     */
    private function cashId(?int $branchId = null): int
    {
        return \App\Services\BranchAccountingService::cashAccountId($branchId);
    }

    /**
     * Get bank account ID for a branch (falls back to generic 1100.00).
     */
    private function bankId(?int $branchId = null): int
    {
        return \App\Services\BranchAccountingService::bankAccountId($branchId);
    }

    /**
     * Resolve cash or bank account ID based on payment method + branch.
     */
    private function cashOrBankId(string $paymentMethod, ?int $branchId = null): int
    {
        return \App\Services\BranchAccountingService::cashOrBankId($paymentMethod, $branchId);
    }

    /**
     * Resolve branch_id for a journal entry from multiple fallback sources:
     *   1. Source model warehouse → warehouse.branch_id
     *   2. Authenticated user → user.branch_id
     *   3. System default_branch_id setting
     *   4. null (consolidated / single-branch company)
     */
    private function resolveBranchId(mixed $source = null): ?int
    {
        // 1. Source model has warehouse_id → derive branch from warehouse
        if ($source && isset($source->warehouse_id) && $source->warehouse_id) {
            $branchId = \App\Models\Warehouse::where('id', $source->warehouse_id)
                ->value('branch_id');
            if ($branchId) {
                return (int) $branchId;
            }
        }

        // 2. Authenticated user's branch
        /** @var \App\Models\User|null $user */
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user && $user->branch_id) {
            return (int) $user->branch_id;
        }

        // 3. System default branch
        $defaultId = (int) Setting::get('default_branch_id', 0);
        return $defaultId ?: null;
    }

    private function account(string $code): Account
    {
        if (!isset($this->cache[$code])) {
            $account = Account::where('code', $code)->where('is_active', true)->first();

            if (!$account) {
                throw new RuntimeException("الحساب غير موجود أو غير نشط: كود [{$code}] — راجع إعدادات الحسابات");
            }
            if ($account->is_header) {
                throw new RuntimeException("لا يمكن الترحيل لحساب رئيسي (إجمالي): [{$code}] {$account->name}");
            }

            $this->cache[$code] = $account;
        }

        return $this->cache[$code];
    }

    /**
     * Validate balance, check period lock, then persist a JournalEntry with its lines.
     *
     * This is the SINGLE enforcement point for period locking.
     * Every post*() method flows through here.
     *
     * @param  array<string, mixed>                                                       $header
     * @param  list<array{account_id:int,debit:float,credit:float,line_description:string}> $lines
     */
    /**
     * Public wrapper — allows external services (e.g. CheckPostingService)
     * to post entries through the same period-lock and balance checks.
     */
    public function buildEntryPublic(array $header, array $lines): JournalEntry
    {
        return $this->buildEntry($header, $lines);
    }

    private function buildEntry(array $header, array $lines): JournalEntry
    {
        // ── 1. Period lock check ─────────────────────────────────────────────
        $entryDate = $header['entry_date'] ?? now()->toDateString();
        \App\Services\PeriodLockService::assertOpen($entryDate);

        // ── 2. Balance check ─────────────────────────────────────────────────
        $debits  = round(array_sum(array_column($lines, 'debit')),  2);
        $credits = round(array_sum(array_column($lines, 'credit')), 2);

        if (abs($debits - $credits) > 0.005) {
            throw new RuntimeException(
                "القيد غير متوازن — مجموع المدين: {$debits} / مجموع الدائن: {$credits}"
            );
        }

        /** @var JournalEntry $entry */
        $entry = JournalEntry::create(array_merge($header, [
            'user_id'   => \Illuminate\Support\Facades\Auth::id() ?? 1,
            'posted_at' => now(),
        ]));

        foreach ($lines as $line) {
            JournalEntryLine::create(array_merge(['journal_entry_id' => $entry->id], $line));
        }

        return $entry;
    }

    // ── public posting methods ────────────────────────────────────────────────

    /**
     * Step 2 + 9 — Post a completed sale to the GL.
     *
     * Cash sale:    DR Cash/Bank = total_amount
     * Credit sale:  DR AR        = total_amount
     * (discount>0): DR Sales Discount = discount
     *               CR Sales Revenue  = subtotal
     * (tax>0):      CR Tax Payable    = tax
     *               DR COGS           = Σ(qty × cost_price_snapshot)
     *               CR Inventory      = Σ(qty × cost_price_snapshot)
     */
    public function postSale(Sale $sale): JournalEntry
    {
        if ($sale->is_posted) {
            throw new RuntimeException("فاتورة البيع [{$sale->invoice_number}] مُرحَّلة مسبقاً في دفتر الأستاذ");
        }

        $sale->loadMissing('items', 'customer');

        $total    = round((float) $sale->total_amount, 2);
        $subtotal = round((float) $sale->subtotal,     2);
        $discount = round((float) $sale->discount,     2);
        $tax      = round((float) $sale->tax,          2);
        // التكلفة تشمل الكمية المجانية (البونص) — تخرج من المخزون وتُحمَّل على تكلفة المبيعات
        $cogs     = round(
            $sale->items->sum(fn($i) => ((float) $i->quantity + (float) ($i->bonus_qty ?? 0)) * (float) $i->cost_price),
            2
        );

        // Resolve per-branch accounts for cash/bank
        $saleBranchId  = $this->resolveBranchId($sale);
        $arCode        = Setting::get('account_ar_code',          '1200');
        $inventoryCode = Setting::get('account_inventory_code',   '1300');
        $taxCode       = Setting::get('account_tax_payable_code', '2200');
        $salesCode     = Setting::get('account_sales_code',       '4000');
        $discountCode  = Setting::get('account_discount_code',    '4300');
        $cogsCode      = Setting::get('account_cogs_code',        '5000');

        $balanceUsed  = round((float) ($sale->balance_used ?? 0), 2);
        $depositsCode = Setting::get('account_customer_deposits_code', '2050');

        $lines = [];

        // ── Mixed tender (الدفع المختلط): جزء الشيكات يُرحَّل لحساب شيكات تحت التحصيل ──
        // additive: cheque_amount = 0 ⇒ سلوك مطابق تماماً للقديم
        $chequeAmt = round((float) ($sale->cheque_amount ?? 0), 2);
        if ($chequeAmt > 0) {
            $chkRecvCode = Setting::get('account_checks_receivable_code', '1120');
            $lines[] = [
                'account_id'       => $this->account($chkRecvCode)->id,
                'debit'            => $chequeAmt,
                'credit'           => 0,
                'line_description' => 'شيكات تحت التحصيل – ' . ($sale->customer?->name ?? 'عميل'),
            ];
        }

        // ── Part paid from deposit balance ──
        if ($balanceUsed > 0) {
            $lines[] = [
                'account_id'       => $this->account($depositsCode)->id,
                'debit'            => $balanceUsed,
                'credit'           => 0,
                'line_description' => 'خصم رصيد إيداع – ' . ($sale->customer?->name ?? 'عميل'),
            ];
        }

        // ── Debit: Cash/Bank (per-branch), or AR ──
        if ($sale->is_credit) {
            $arPortion = round($total - $chequeAmt - $balanceUsed, 2);
            if ($arPortion > 0) {
                $lines[] = [
                    'account_id'       => $this->account($arCode)->id,
                    'debit'            => $arPortion,
                    'credit'           => 0,
                    'line_description' => 'ذمم عميل – ' . ($sale->customer?->name ?? 'عميل'),
                ];
            }
        } else {
            // Remaining paid in cash/card (after deposit + cheque portion)
            $cashPaid = round($total - $balanceUsed - $chequeAmt, 2);
            if ($cashPaid > 0 && $sale->payment_method !== 'deposit_balance') {
                $drAccountId = $this->cashOrBankId($sale->payment_method, $saleBranchId);
                $label  = match ($sale->payment_method) {
                    'card'          => 'تحصيل بطاقة',
                    'mobile_wallet' => 'تحصيل محفظة',
                    default         => 'تحصيل نقدي',
                };
                $lines[] = [
                    'account_id'       => $drAccountId,
                    'debit'            => $cashPaid,
                    'credit'           => 0,
                    'line_description' => $label,
                ];
            }
        }

        // ── Debit: Sales Discount (contra-revenue, normal debit balance) ──
        if ($discount > 0) {
            $lines[] = [
                'account_id'       => $this->account($discountCode)->id,
                'debit'            => $discount,
                'credit'           => 0,
                'line_description' => 'خصم مبيعات – ' . $sale->invoice_number,
            ];
        }

        // ── Credit: Sales Revenue ──
        $lines[] = [
            'account_id'       => $this->account($salesCode)->id,
            'debit'            => 0,
            'credit'           => $subtotal,
            'line_description' => 'إيراد مبيعات – ' . $sale->invoice_number,
        ];

        // ── Credit: Tax Payable ──
        if ($tax > 0) {
            $lines[] = [
                'account_id'       => $this->account($taxCode)->id,
                'debit'            => 0,
                'credit'           => $tax,
                'line_description' => 'ضريبة قيمة مضافة مستحقة',
            ];
        }

        // ── COGS entry ──
        if ($cogs > 0) {
            $lines[] = [
                'account_id'       => $this->account($cogsCode)->id,
                'debit'            => $cogs,
                'credit'           => 0,
                'line_description' => 'تكلفة بضاعة مباعة – ' . $sale->invoice_number,
            ];
            $lines[] = [
                'account_id'       => $this->account($inventoryCode)->id,
                'debit'            => 0,
                'credit'           => $cogs,
                'line_description' => 'إقفال مخزون – ' . $sale->invoice_number,
            ];
        }

        $entry = $this->buildEntry([
            'entry_date'  => $sale->created_at->toDateString(),
            'reference'   => $sale->invoice_number,
            'source_type' => Sale::class,
            'source_id'   => $sale->id,
            'branch_id'   => $this->resolveBranchId($sale),
            'description' => 'قيد مبيعات – ' . $sale->invoice_number,
        ], $lines);

        $sale->update(['is_posted' => true]);

        return $entry;
    }

    /**
     * مرتجع مبيعات — Sales Return
     *
     * cash/bank refund:
     *   DR مردودات مبيعات (4100) = total
     *   CR صندوق/بنك (1000/1100) = total
     *
     * credit note (reduces AR):
     *   DR مردودات مبيعات (4100) = total
     *   CR ذمم عملاء (1200)      = total
     *
     * Inventory reversal (all cases):
     *   DR مخزون (1300)          = COGS
     *   CR تكلفة بضاعة مباعة (5000) = COGS
     */
    public function postSaleReturn(SaleReturn $ret): JournalEntry
    {
        $ret->loadMissing('items.product', 'customer');

        $total = round((float) $ret->total_amount, 2);
        $cogs  = round(
            $ret->items->sum(fn($i) => $i->quantity * (float) $i->cost_price),
            2
        );

        $retBranchId      = $this->resolveBranchId($ret);
        $salesReturnsCode = Setting::get('account_sales_returns_code', '4200');
        $arCode           = Setting::get('account_ar_code',            '1200');
        $inventoryCode    = Setting::get('account_inventory_code',     '1300');
        $cogsCode         = Setting::get('account_cogs_code',          '5000');

        $customerName = $ret->customer?->name ?? 'عميل';

        $lines = [];

        // ── Debit: Sales Returns (contra-revenue) ──
        $lines[] = [
            'account_id'       => $this->account($salesReturnsCode)->id,
            'debit'            => $total,
            'credit'           => 0,
            'line_description' => 'مردودات مبيعات – ' . $ret->return_number,
        ];

        // ── Credit: Cash/Bank or AR depending on refund method ──
        if ($ret->refund_method === 'credit_note') {
            $lines[] = [
                'account_id'       => $this->account($arCode)->id,
                'debit'            => 0,
                'credit'           => $total,
                'line_description' => 'تخفيض ذمة عميل – ' . $customerName,
            ];
        } else {
            $crAccountId = $this->cashOrBankId($ret->refund_method, $retBranchId);
            $label  = $ret->refund_method === 'bank' ? 'صرف استرداد بنكي' : 'صرف استرداد نقدي';
            $lines[] = [
                'account_id'       => $crAccountId,
                'debit'            => 0,
                'credit'           => $total,
                'line_description' => $label . ' – ' . ($ret->customer?->name ?? ''),
            ];
        }

        // ── Inventory reversal (goods back in stock) ──
        if ($cogs > 0) {
            $lines[] = [
                'account_id'       => $this->account($inventoryCode)->id,
                'debit'            => $cogs,
                'credit'           => 0,
                'line_description' => 'إعادة بضاعة للمخزون – ' . $ret->return_number,
            ];
            $lines[] = [
                'account_id'       => $this->account($cogsCode)->id,
                'debit'            => 0,
                'credit'           => $cogs,
                'line_description' => 'عكس تكلفة بضاعة مباعة – ' . $ret->return_number,
            ];
        }

        return $this->buildEntry([
            'entry_date'  => $ret->return_date->toDateString(),
            'reference'   => $ret->return_number,
            'source_type' => SaleReturn::class,
            'source_id'   => $ret->id,
            'branch_id'   => $this->resolveBranchId($ret),
            'description' => 'قيد مرتجع مبيعات – ' . $ret->return_number
                             . ($ret->customer ? ' / ' . $customerName : ''),
        ], $lines);
    }

    /**
     * مرتجع مشتريات — Purchase Return (perpetual inventory)
     *
     * ap_deduction: DR AP (2000)      = total  |  CR Inventory (1300) = total
     * cash refund:  DR Cash (1000)    = total  |  CR Inventory (1300) = total
     * bank refund:  DR Bank (1100)    = total  |  CR Inventory (1300) = total
     */
    public function postPurchaseReturn(PurchaseReturn $ret): JournalEntry
    {
        $ret->loadMissing('items', 'supplier');

        $prBranchId    = $this->resolveBranchId($ret);
        $total         = round((float) $ret->total_amount, 2);
        $apCode        = Setting::get('account_ap_code',        '2000');
        $inventoryCode = Setting::get('account_inventory_code', '1300');
        $supplierName  = $ret->supplier?->name ?? 'مورد';

        // Debit side: what the supplier owes us (AP reduction or cash/bank inflow)
        $drAccountId = match($ret->refund_method) {
            'cash'  => $this->cashId($prBranchId),
            'bank'  => $this->bankId($prBranchId),
            default => $this->account($apCode)->id,   // ap_deduction
        };
        $drLabel = match($ret->refund_method) {
            'cash'  => 'استرداد نقدي من مورد',
            'bank'  => 'استرداد بنكي من مورد',
            default => 'تخفيض ذمة مورد – ' . $supplierName,
        };

        $lines = [
            [
                'account_id'       => $drAccountId,
                'debit'            => $total,
                'credit'           => 0,
                'line_description' => $drLabel,
            ],
            [
                'account_id'       => $this->account($inventoryCode)->id,
                'debit'            => 0,
                'credit'           => $total,
                'line_description' => 'إخراج بضاعة مرتجعة للمورد – ' . $ret->return_number,
            ],
        ];

        return $this->buildEntry([
            'entry_date'  => $ret->return_date->toDateString(),
            'reference'   => $ret->return_number,
            'source_type' => PurchaseReturn::class,
            'source_id'   => $ret->id,
            'branch_id'   => $this->resolveBranchId($ret),
            'description' => 'قيد مرتجع مشتريات – ' . $ret->return_number . ' / ' . $supplierName,
        ], $lines);
    }

    /**
     * Step 3 — Post a purchase to the GL.
     *
     * DR 1300 Inventory = total_amount
     * CR 2000 AP        = unpaid portion  (if > 0)
     * CR 1000 Cash      = paid portion    (if > 0)
     */
    public function postPurchase(Purchase $purchase): JournalEntry
    {
        if ($purchase->is_posted) {
            throw new RuntimeException("فاتورة الشراء [{$purchase->invoice_number}] مُرحَّلة مسبقاً في دفتر الأستاذ");
        }

        $purchase->loadMissing('supplier');

        $total  = round((float) $purchase->total_amount, 2);
        $tax    = round((float) ($purchase->tax_amount ?? 0), 2);   // ضريبة مدخلات
        $paid   = round((float) $purchase->paid_amount,  2);
        $unpaid = round($total - $paid, 2);

        $purchBranchId = $this->resolveBranchId($purchase);
        $apCode        = Setting::get('account_ap_code',        '2000');
        $inventoryCode = Setting::get('account_inventory_code', '1300');

        $supplierName = $purchase->supplier?->name ?? 'مورد';
        $lines = [];

        // ── Debit: Inventory (net of recoverable input VAT — IAS 2) ──
        $lines[] = [
            'account_id'       => $this->account($inventoryCode)->id,
            'debit'            => round($total - $tax, 2),
            'credit'           => 0,
            'line_description' => 'مشتريات بضاعة – ' . $purchase->invoice_number,
        ];

        // ── Debit: ضريبة المدخلات (أصل قابل للاسترداد) ──
        if ($tax > 0) {
            $taxInputCode = Setting::get('account_tax_input_code', '1260');
            $lines[] = [
                'account_id'       => $this->account($taxInputCode)->id,
                'debit'            => $tax,
                'credit'           => 0,
                'line_description' => 'ضريبة مدخلات – ' . $purchase->invoice_number,
            ];
        }

        // ── Credit: AP for unpaid portion ──
        if ($unpaid > 0) {
            $lines[] = [
                'account_id'       => $this->account($apCode)->id,
                'debit'            => 0,
                'credit'           => $unpaid,
                'line_description' => 'ذمة مورد – ' . $supplierName,
            ];
        }

        // ── Credit: Cash for any immediate payment (branch cash account) ──
        if ($paid > 0) {
            $lines[] = [
                'account_id'       => $this->cashId($purchBranchId),
                'debit'            => 0,
                'credit'           => $paid,
                'line_description' => 'دفعة فورية عند الشراء',
            ];
        }

        $entry = $this->buildEntry([
            'entry_date'  => $purchase->created_at->toDateString(),
            'reference'   => $purchase->invoice_number,
            'source_type' => Purchase::class,
            'source_id'   => $purchase->id,
            'branch_id'   => $this->resolveBranchId($purchase),
            'description' => 'قيد مشتريات – ' . $purchase->invoice_number . ' / ' . $supplierName,
        ], $lines);

        $purchase->update(['is_posted' => true]);

        return $entry;
    }

    /**
     * Step 4 — Post a supplier payment to the GL.
     *
     * DR 2000 AP   = amount
     * CR 1000/1100 = amount (cash or bank)
     */
    public function postSupplierPayment(SupplierPayment $payment): JournalEntry
    {
        $payment->loadMissing('supplier', 'purchase');

        $spBranchId   = $this->resolveBranchId($payment->purchase ?? null);
        $amount       = round((float) $payment->amount, 2);
        $apCode       = Setting::get('account_ap_code', '2000');
        $crAccountId  = $this->cashOrBankId($payment->payment_method, $spBranchId);
        $supplierName = $payment->supplier?->name ?? 'مورد';
        $ref          = $payment->purchase?->invoice_number ?? ('PMT-' . $payment->id);

        $lines = [
            [
                'account_id'       => $this->account($apCode)->id,
                'debit'            => $amount,
                'credit'           => 0,
                'line_description' => 'سداد ذمة مورد – ' . $supplierName,
            ],
            [
                'account_id'       => $crAccountId,
                'debit'            => 0,
                'credit'           => $amount,
                'line_description' => 'دفع للمورد – ' . $supplierName,
            ],
        ];

        return $this->buildEntry([
            'entry_date'  => Carbon::parse($payment->paid_at),
            'reference'   => $ref,
            'source_type' => SupplierPayment::class,
            'source_id'   => $payment->id,
            'branch_id'   => $this->resolveBranchId($payment->purchase ?? null),
            'description' => 'سداد مورد – ' . $supplierName
                             . ($payment->purchase ? ' / فاتورة ' . $ref : ''),
        ], $lines);
    }

    /**
     * Step 5 — Post a customer payment to the GL.
     *
     * DR 1000/1100 Cash or Bank = amount received
     * CR 1200 AR               = amount (reduces receivable)
     */
    public function postCustomerPayment(\App\Models\CustomerPayment $payment): JournalEntry
    {
        $payment->loadMissing('customer', 'sale');

        $cpBranchId   = $this->resolveBranchId($payment->sale ?? null);
        $amount       = round((float) $payment->amount, 2);
        $arCode       = Setting::get('account_ar_code', '1200');
        $drAccountId  = $this->cashOrBankId($payment->payment_method, $cpBranchId);
        $customerName = $payment->customer?->name ?? 'عميل';
        $ref          = $payment->sale?->invoice_number ?? ('CPM-' . $payment->id);

        $lines = [
            [
                'account_id'       => $drAccountId,
                'debit'            => $amount,
                'credit'           => 0,
                'line_description' => 'تحصيل من عميل – ' . $customerName,
            ],
            [
                'account_id'       => $this->account($arCode)->id,
                'debit'            => 0,
                'credit'           => $amount,
                'line_description' => 'تسوية ذمة عميل – ' . $customerName,
            ],
        ];

        return $this->buildEntry([
            'entry_date'  => \Carbon\Carbon::parse($payment->received_at),
            'reference'   => $ref,
            'source_type' => \App\Models\CustomerPayment::class,
            'source_id'   => $payment->id,
            'branch_id'   => $this->resolveBranchId($payment->sale ?? null),
            'description' => 'تحصيل دفعة – ' . $customerName
                             . ($payment->sale ? ' / فاتورة ' . $ref : ''),
        ], $lines);
    }

    /**
     * إيداع / استرداد رصيد عميل — Customer Deposit or Refund
     *
     * إيداع:    DR Cash/Bank (1000/1100) = amount  |  CR Customer Deposits (2050) = amount
     * استرداد:  DR Customer Deposits (2050) = amount  |  CR Cash/Bank = amount
     */
    public function postCustomerDeposit(CustomerDeposit $deposit): JournalEntry
    {
        if ($deposit->is_posted) {
            throw new RuntimeException("هذا السند [{$deposit->voucher_number}] مُرحَّل مسبقاً");
        }

        $deposit->loadMissing('customer');

        $cdBranchId   = $deposit->branch_id ?? $this->resolveBranchId();
        $amount       = round((float) $deposit->amount, 2);
        $depositCode  = Setting::get('account_customer_deposits_code', '2050');
        $cashOrBankId = $this->cashOrBankId($deposit->payment_method, $cdBranchId);
        $customerName = $deposit->customer?->name ?? 'عميل';
        $typeLabel    = $deposit->type === 'deposit' ? 'إيداع' : 'استرداد';

        if ($deposit->type === 'deposit') {
            $lines = [
                [
                    'account_id'       => $cashOrBankId,
                    'debit'            => $amount,
                    'credit'           => 0,
                    'line_description' => 'استلام إيداع من ' . $customerName,
                ],
                [
                    'account_id'       => $this->account($depositCode)->id,
                    'debit'            => 0,
                    'credit'           => $amount,
                    'line_description' => 'إيداع مسبق – ' . $customerName,
                ],
            ];
        } else {
            // Refund: reverse the entries
            $lines = [
                [
                    'account_id'       => $this->account($depositCode)->id,
                    'debit'            => $amount,
                    'credit'           => 0,
                    'line_description' => 'استرداد إيداع – ' . $customerName,
                ],
                [
                    'account_id'       => $cashOrBankId,
                    'debit'            => 0,
                    'credit'           => $amount,
                    'line_description' => 'صرف استرداد لـ ' . $customerName,
                ],
            ];
        }

        $entry = $this->buildEntry([
            'entry_date'  => Carbon::parse($deposit->voucher_date),
            'reference'   => $deposit->voucher_number,
            'source_type' => CustomerDeposit::class,
            'source_id'   => $deposit->id,
            'branch_id'   => $deposit->branch_id ?? $this->resolveBranchId(),
            'description' => $typeLabel . ' رصيد عميل – ' . $deposit->voucher_number . ' / ' . $customerName,
        ], $lines);

        $deposit->update(['is_posted' => true, 'journal_entry_id' => $entry->id]);

        return $entry;
    }

    /**
     * سلفة موظف — Employee Loan disbursement
     * Lines are pre-built by the controller (DR Employee Loans / CR Cash)
     *
     * @param  array<array{account_id:int,debit:float,credit:float,line_description:string}> $lines
     */
    public function postEmployeeLoan(EmployeeLoan $loan, array $lines): JournalEntry
    {
        $loan->loadMissing('employee');
        $empName = $loan->employee?->name ?? 'موظف';

        return $this->buildEntry([
            'entry_date'  => Carbon::parse($loan->loan_date),
            'reference'   => 'LOAN-' . $loan->id,
            'source_type' => EmployeeLoan::class,
            'source_id'   => $loan->id,
            'branch_id'   => $this->resolveBranchId(),
            'description' => 'سلفة موظف – ' . $empName . ' / ' . number_format($loan->amount, 2),
        ], $lines);
    }

    /**
     * مسير رواتب — Payroll Run
     * Lines are pre-built by PayrollController::approve()
     * (DR Salaries Expense / CR Cash + Salaries Payable + Employee Loans).
     *
     * Routes through buildEntry() so period-lock and balance checks
     * apply the same way as every other posting method.
     *
     * @param  array<array{account_id:int,debit:float,credit:float,line_description:string}> $lines
     */
    public function postPayrollRun(PayrollRun $payrollRun, array $lines): JournalEntry
    {
        if ($payrollRun->status !== 'draft') {
            throw new RuntimeException("مسير الرواتب [{$payrollRun->reference}] مُرحَّل مسبقاً في دفتر الأستاذ");
        }

        return $this->buildEntry([
            'entry_date'  => Carbon::parse($payrollRun->pay_date),
            'reference'   => $payrollRun->reference,
            'source_type' => PayrollRun::class,
            'source_id'   => $payrollRun->id,
            'branch_id'   => $payrollRun->branch_id ?? $this->resolveBranchId(),
            'description' => 'مسير رواتب ' . $payrollRun->periodLabel(),
        ], $lines);
    }

    /**
     * سند قبض — Receipt Voucher
     *
     * DR cash_account (صندوق/بنك)            = amount (الصافي المقبوض)
     * DR خصم مصدر مدفوع مقدماً (1250)        = source_discount_amount (إن وجد)
     * CR account (الحساب المقابل)            = amount + source_discount_amount
     *
     * العملة الأجنبية: amount دائماً بالعملة الأساسية (محوَّل وقت الإدخال)،
     * وتُحفظ amount_fc/exchange_rate للمرجعية على السند فقط.
     */
    public function postReceiptVoucher(ReceiptVoucher $voucher): JournalEntry
    {
        if ($voucher->is_posted) {
            throw new RuntimeException("سند القبض [{$voucher->voucher_number}] مُرحَّل مسبقاً في دفتر الأستاذ");
        }

        $voucher->loadMissing('cashAccount', 'account');

        foreach (['cashAccount', 'account'] as $rel) {
            $acc = $voucher->$rel;
            if (!$acc || !$acc->is_active) {
                throw new RuntimeException("حساب غير موجود أو غير نشط في سند القبض");
            }
            if ($acc->is_header) {
                throw new RuntimeException("لا يمكن الترحيل لحساب رئيسي (تجميعي): [{$acc->code}] {$acc->name}");
            }
        }

        $amount         = round((float) $voucher->amount, 2);
        $sourceDiscount = round((float) ($voucher->source_discount_amount ?? 0), 2);

        $lines = [
            [
                'account_id'       => $voucher->cash_account_id,
                'debit'            => $amount,
                'credit'           => 0,
                'line_description' => 'تحصيل من ' . $voucher->received_from,
            ],
        ];

        // خصم المصدر: أصل ضريبي مدين (يُخصم لاحقاً من الضريبة المستحقة)
        if ($sourceDiscount > 0) {
            $sdCode = Setting::get('account_source_discount_code', '1250');
            $lines[] = [
                'account_id'       => $this->account($sdCode)->id,
                'debit'            => $sourceDiscount,
                'credit'           => 0,
                'line_description' => 'خصم مصدر ' . rtrim(rtrim(number_format((float) $voucher->source_discount_rate, 2), '0'), '.') . '% – ' . $voucher->voucher_number,
            ];
        }

        $lines[] = [
            'account_id'       => $voucher->account_id,
            'debit'            => 0,
            'credit'           => round($amount + $sourceDiscount, 2),
            'line_description' => 'سند قبض – ' . $voucher->voucher_number,
        ];

        $entry = $this->buildEntry([
            'entry_date'  => Carbon::parse($voucher->voucher_date),
            'reference'   => $voucher->voucher_number,
            'source_type' => ReceiptVoucher::class,
            'source_id'   => $voucher->id,
            'branch_id'   => $voucher->branch_id ?? $this->resolveBranchId(),
            'description' => 'سند قبض – ' . $voucher->voucher_number . ' / ' . $voucher->received_from,
        ], $lines);

        $voucher->update(['is_posted' => true, 'journal_entry_id' => $entry->id]);

        return $entry;
    }

    /**
     * سند صرف — Payment Voucher
     *
     * DR account      (الحساب المقابل) = amount
     * CR cash_account (صندوق/بنك) = amount
     */
    public function postPaymentVoucher(PaymentVoucher $voucher): JournalEntry
    {
        if ($voucher->is_posted) {
            throw new RuntimeException("سند الصرف [{$voucher->voucher_number}] مُرحَّل مسبقاً في دفتر الأستاذ");
        }

        $voucher->loadMissing('cashAccount', 'account');

        foreach (['cashAccount', 'account'] as $rel) {
            $acc = $voucher->$rel;
            if (!$acc || !$acc->is_active) {
                throw new RuntimeException("حساب غير موجود أو غير نشط في سند الصرف");
            }
            if ($acc->is_header) {
                throw new RuntimeException("لا يمكن الترحيل لحساب رئيسي (تجميعي): [{$acc->code}] {$acc->name}");
            }
        }

        $amount = round((float) $voucher->amount, 2);

        $lines = [
            [
                'account_id'       => $voucher->account_id,
                'debit'            => $amount,
                'credit'           => 0,
                'line_description' => 'سند صرف – ' . $voucher->voucher_number,
            ],
            [
                'account_id'       => $voucher->cash_account_id,
                'debit'            => 0,
                'credit'           => $amount,
                'line_description' => 'دفع لـ ' . $voucher->paid_to,
            ],
        ];

        $entry = $this->buildEntry([
            'entry_date'  => Carbon::parse($voucher->voucher_date),
            'reference'   => $voucher->voucher_number,
            'source_type' => PaymentVoucher::class,
            'source_id'   => $voucher->id,
            'branch_id'   => $voucher->branch_id ?? $this->resolveBranchId(),
            'description' => 'سند صرف – ' . $voucher->voucher_number . ' / ' . $voucher->paid_to,
        ], $lines);

        $voucher->update(['is_posted' => true, 'journal_entry_id' => $entry->id]);

        return $entry;
    }

    /**
     * فاتورة مصروفات — Expense Invoice (Accrual Basis)
     *
     * On invoice creation:
     *   DR expense_account  = total_amount   (e.g., rent, utilities, maintenance)
     *   CR AP (2000)        = total_amount   (liability — we owe the vendor)
     *
     * This records the OBLIGATION when invoice is received, before payment.
     * Compare with purchase invoices (DR inventory) and payment vouchers (DR AP / CR cash).
     */
    public function postExpenseInvoice(ExpenseInvoice $invoice): JournalEntry
    {
        if ($invoice->is_posted) {
            throw new RuntimeException("فاتورة المصروف [{$invoice->invoice_number}] مُرحَّلة مسبقاً");
        }

        $invoice->loadMissing('expenseAccount');

        $amount       = round((float) $invoice->total_amount, 2);
        $tax          = round((float) ($invoice->tax_amount ?? 0), 2);   // ضريبة مدخلات
        $apCode       = Setting::get('account_ap_code', '2000');
        $vendorName   = $invoice->vendor_name;

        $lines = [
            [
                'account_id'       => $invoice->expense_account_id,
                'debit'            => round($amount - $tax, 2),
                'credit'           => 0,
                'line_description' => ($invoice->expenseAccount->name ?? 'مصروف')
                                      . ' — ' . $vendorName,
            ],
        ];

        if ($tax > 0) {
            $taxInputCode = Setting::get('account_tax_input_code', '1260');
            $lines[] = [
                'account_id'       => $this->account($taxInputCode)->id,
                'debit'            => $tax,
                'credit'           => 0,
                'line_description' => 'ضريبة مدخلات — ' . $invoice->invoice_number,
            ];
        }

        $lines[] = [
            'account_id'       => $this->account($apCode)->id,
            'debit'            => 0,
            'credit'           => $amount,
            'line_description' => 'فاتورة مصروف مستلمة — ' . $vendorName
                                  . ' / ' . $invoice->invoice_number,
        ];

        return $this->buildEntry([
            'entry_date'  => $invoice->invoice_date->toDateString(),
            'reference'   => $invoice->invoice_number,
            'source_type' => ExpenseInvoice::class,
            'source_id'   => $invoice->id,
            'branch_id'   => $invoice->branch_id ?? $this->resolveBranchId(),
            'description' => 'فاتورة مصروفات — ' . $invoice->invoice_number
                             . ' / ' . $vendorName,
        ], $lines);
    }

    /**
     * فاتورة إيراد الخدمات — Service Revenue Invoice (IFRS 15)
     *
     *   DR Cash/Bank (نقدي) أو AR (آجل) = الإجمالي
     *   CR إيرادات الخدمات              = الصافي (بلا ضريبة)
     *   CR ضريبة قيمة مضافة مستحقة      = الضريبة (مخرجات)
     */
    public function postServiceInvoice(\App\Models\ServiceInvoice $invoice): JournalEntry
    {
        if ($invoice->is_posted) {
            throw new RuntimeException("فاتورة الخدمات [{$invoice->invoice_number}] مُرحَّلة مسبقاً");
        }

        $invoice->loadMissing('serviceAccount', 'customer');

        $total = round((float) $invoice->total_amount, 2);
        $tax   = round((float) ($invoice->tax_amount ?? 0), 2);
        $net   = round($total - $tax, 2);

        $arCode      = Setting::get('account_ar_code',              '1200');
        $cashCode    = Setting::get('account_cash_code',           '1000');
        $taxCode     = Setting::get('account_tax_payable_code',    '2200');
        $serviceCode = Setting::get('account_service_revenue_code', '4200');
        $serviceAcctId = $invoice->service_account_id ?? $this->account($serviceCode)->id;

        $lines = [];

        $lines[] = [
            'account_id'       => $invoice->is_credit ? $this->account($arCode)->id : $this->account($cashCode)->id,
            'debit'            => $total,
            'credit'           => 0,
            'line_description' => ($invoice->is_credit ? 'ذمم عميل – ' : 'تحصيل نقدي – ') . $invoice->partyName(),
        ];

        $lines[] = [
            'account_id'       => $serviceAcctId,
            'debit'            => 0,
            'credit'           => $net,
            'line_description' => 'إيراد خدمات – ' . $invoice->invoice_number,
        ];

        if ($tax > 0) {
            $lines[] = [
                'account_id'       => $this->account($taxCode)->id,
                'debit'            => 0,
                'credit'           => $tax,
                'line_description' => 'ضريبة قيمة مضافة مستحقة – ' . $invoice->invoice_number,
            ];
        }

        $entry = $this->buildEntry([
            'entry_date'  => $invoice->invoice_date->toDateString(),
            'reference'   => $invoice->invoice_number,
            'source_type' => \App\Models\ServiceInvoice::class,
            'source_id'   => $invoice->id,
            'branch_id'   => $invoice->branch_id ?? $this->resolveBranchId(),
            'description' => 'فاتورة إيراد خدمات – ' . $invoice->invoice_number,
        ], $lines);

        $invoice->update(['is_posted' => true, 'journal_entry_id' => $entry->id]);

        return $entry;
    }

    /**
     * دفع فاتورة مصروفات — Expense Payment
     *
     * On payment:
     *   DR AP (2000)        = amount   (clears the liability)
     *   CR Cash/Bank        = amount   (cash goes out)
     */
    public function postExpensePayment(ExpensePayment $payment): JournalEntry
    {
        $payment->loadMissing('expenseInvoice');

        $epBranchId   = $payment->branch_id ?? $this->resolveBranchId();
        $amount       = round((float) $payment->amount, 2);
        $apCode       = Setting::get('account_ap_code', '2000');
        $crAccountId  = $this->cashOrBankId($payment->payment_method, $epBranchId);
        $label        = $payment->payment_method === 'bank' ? 'دفع بنكي' : 'دفع نقدي';
        $vendorName   = $payment->expenseInvoice->vendor_name ?? 'مورد';

        $lines = [
            [
                'account_id'       => $this->account($apCode)->id,
                'debit'            => $amount,
                'credit'           => 0,
                'line_description' => 'سداد فاتورة مصروف — ' . $vendorName,
            ],
            [
                'account_id'       => $crAccountId,
                'debit'            => 0,
                'credit'           => $amount,
                'line_description' => $label . ' — ' . $vendorName,
            ],
        ];

        return $this->buildEntry([
            'entry_date'  => $payment->payment_date->toDateString(),
            'reference'   => $payment->expenseInvoice->invoice_number ?? 'EXP-PAY-' . $payment->id,
            'source_type' => ExpensePayment::class,
            'source_id'   => $payment->id,
            'branch_id'   => $payment->branch_id ?? $this->resolveBranchId(),
            'description' => 'دفع فاتورة مصروفات — ' . $vendorName,
        ], $lines);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FIXED ASSETS GL POSTINGS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * شراء أصل ثابت — Fixed Asset Purchase
     *
     * DR asset_account (1510/1520) = purchase_cost
     * CR cash (1000) or bank (1100) = purchase_cost
     *
     * Note: If purchased on credit (AP), caller should change CR to AP (2000).
     * Default here assumes immediate cash/bank payment.
     */
    public function postAssetPurchase(FixedAsset $asset, string $paymentMethod = 'cash'): JournalEntry
    {
        $asset->loadMissing('category.assetAccount');

        $assetAccountId  = $asset->category->asset_account_id;
        $amount          = round((float) $asset->purchase_cost, 2);
        $assetBranchId   = $asset->branch_id ?? $this->resolveBranchId();
        $crAccountId     = $this->cashOrBankId($paymentMethod, $assetBranchId);

        $lines = [
            [
                'account_id'       => $assetAccountId,
                'debit'            => $amount,
                'credit'           => 0,
                'line_description' => 'شراء أصل ثابت — ' . $asset->name,
            ],
            [
                'account_id'       => $crAccountId,
                'debit'            => 0,
                'credit'           => $amount,
                'line_description' => ($paymentMethod === 'bank' ? 'دفع بنكي' : 'دفع نقدي')
                                      . ' — ' . $asset->asset_code,
            ],
        ];

        return $this->buildEntry([
            'entry_date'  => $asset->purchase_date->toDateString(),
            'reference'   => $asset->asset_code,
            'source_type' => FixedAsset::class,
            'source_id'   => $asset->id,
            'branch_id'   => $asset->branch_id ?? $this->resolveBranchId(),
            'description' => 'قيد شراء أصل ثابت — ' . $asset->name,
        ], $lines);
    }

    /**
     * قيد استهلاك شهري — Monthly Depreciation
     *
     * DR depreciation_expense_account (6400) = depreciation_amount
     * CR accumulated_depreciation    (1600)  = depreciation_amount
     */
    public function postAssetDepreciation(
        FixedAsset $asset,
        int        $year,
        int        $month,
        float      $amount
    ): JournalEntry {
        $asset->loadMissing('category');

        $depExpAccountId  = $asset->category->depreciation_expense_account_id;
        $accumDepAccountId= $asset->category->accumulated_dep_account_id;
        $periodLabel      = $this->monthName($month) . ' ' . $year;

        $lines = [
            [
                'account_id'       => $depExpAccountId,
                'debit'            => $amount,
                'credit'           => 0,
                'line_description' => 'مصروف استهلاك — ' . $asset->name . ' / ' . $periodLabel,
            ],
            [
                'account_id'       => $accumDepAccountId,
                'debit'            => 0,
                'credit'           => $amount,
                'line_description' => 'مجمع استهلاك — ' . $asset->name . ' / ' . $periodLabel,
            ],
        ];

        return $this->buildEntry([
            'entry_date'  => now()->setDate($year, $month, 1)->endOfMonth()->toDateString(),
            'reference'   => $asset->asset_code . '-DEP-' . $year . str_pad($month, 2, '0', STR_PAD_LEFT),
            'source_type' => AssetDepreciationEntry::class,
            'source_id'   => $asset->id,
            'branch_id'   => $asset->branch_id ?? $this->resolveBranchId(),
            'description' => 'استهلاك — ' . $asset->name . ' / ' . $periodLabel,
        ], $lines);
    }

    /**
     * بيع / استبعاد أصل ثابت — Asset Disposal
     *
     * DR cash/bank             = sale_price              (if sold)
     * DR accumulated_dep (1600) = total accumulated dep
     * CR asset_account (1510)   = purchase_cost
     * ± DR/CR gain/loss account = difference
     *
     * Gain: sale_price > (cost - accum_dep) → CR أرباح بيع أصول (4150)
     * Loss: sale_price < (cost - accum_dep) → DR خسائر بيع أصول (6520)
     */
    public function postAssetDisposal(
        FixedAsset $asset,
        float      $salePrice,
        string     $paymentMethod = 'cash'
    ): JournalEntry {
        $asset->loadMissing('category');

        $cost            = round((float) $asset->purchase_cost, 2);
        $accumDep        = round((float) $asset->accumulated_depreciation, 2);
        $nbv             = round($cost - $accumDep, 2);
        $salePrice       = round($salePrice, 2);
        $gainLoss        = round($salePrice - $nbv, 2);

        $disposalBranchId = $asset->branch_id ?? $this->resolveBranchId();
        $assetAccountId   = $asset->category->asset_account_id;
        $accumDepAccId    = $asset->category->accumulated_dep_account_id;
        $gainCode         = Setting::get('account_asset_gain_code', '4150');
        $lossCode         = Setting::get('account_asset_loss_code', '6520');
        $cashOrBankAccId  = $this->cashOrBankId($paymentMethod, $disposalBranchId);

        $lines = [];

        // Cash/bank inflow (if any sale proceeds)
        if ($salePrice > 0) {
            $lines[] = [
                'account_id'       => $cashOrBankAccId,
                'debit'            => $salePrice,
                'credit'           => 0,
                'line_description' => 'حصيلة بيع أصل — ' . $asset->name,
            ];
        }

        // Remove accumulated depreciation
        if ($accumDep > 0) {
            $lines[] = [
                'account_id'       => $accumDepAccId,
                'debit'            => $accumDep,
                'credit'           => 0,
                'line_description' => 'إزالة مجمع استهلاك — ' . $asset->name,
            ];
        }

        // Remove asset at cost
        $lines[] = [
            'account_id'       => $assetAccountId,
            'debit'            => 0,
            'credit'           => $cost,
            'line_description' => 'إزالة أصل ثابت — ' . $asset->name,
        ];

        // Gain or Loss
        if (abs($gainLoss) > 0.005) {
            if ($gainLoss > 0) {
                $lines[] = [
                    'account_id'       => $this->account($gainCode)->id,
                    'debit'            => 0,
                    'credit'           => $gainLoss,
                    'line_description' => 'ربح بيع أصل — ' . $asset->name,
                ];
            } else {
                $lines[] = [
                    'account_id'       => $this->account($lossCode)->id,
                    'debit'            => abs($gainLoss),
                    'credit'           => 0,
                    'line_description' => 'خسارة بيع أصل — ' . $asset->name,
                ];
            }
        }

        return $this->buildEntry([
            'entry_date'  => $asset->disposal_date?->toDateString() ?? now()->toDateString(),
            'reference'   => $asset->asset_code . '-DISP',
            'source_type' => FixedAsset::class,
            'source_id'   => $asset->id,
            'branch_id'   => $asset->branch_id ?? $this->resolveBranchId(),
            'description' => 'قيد استبعاد أصل ثابت — ' . $asset->name,
        ], $lines);
    }

    /**
     * إقفال وردية الكاشير — Cash Shift Close
     *
     * Only posts if |variance| ≥ 0.01 (skip zero-variance shifts).
     *
     * Short cash (variance < 0):  DR عجز نقدي - ورديات (6530)  = |variance|
     *                              CR صندوق (1000.XX)            = |variance|
     *
     * Over cash  (variance > 0):  DR صندوق (1000.XX)            = variance
     *                              CR فائض نقدي - ورديات (4160) = variance
     */
    public function postShiftClose(\App\Models\CashShift $shift): ?JournalEntry
    {
        $variance = round((float) $shift->variance_amount, 2);

        if (abs($variance) < 0.01) {
            return null;   // no GL entry for exact match
        }

        $branchId   = $shift->branch_id;
        $cashAccId  = $this->cashId($branchId);

        $shortageCode = Setting::get('account_pos_cash_shortage_code', '6530');
        $overageCode  = Setting::get('account_pos_cash_overage_code',  '4160');

        if ($variance < 0) {
            // Short: cashier handed in less than expected
            $lines = [
                [
                    'account_id'       => $this->account($shortageCode)->id,
                    'debit'            => abs($variance),
                    'credit'           => 0,
                    'line_description' => 'عجز نقدي — وردية #' . $shift->id,
                ],
                [
                    'account_id'       => $cashAccId,
                    'debit'            => 0,
                    'credit'           => abs($variance),
                    'line_description' => 'تسوية عجز نقدي — وردية #' . $shift->id,
                ],
            ];
        } else {
            // Over: cashier handed in more than expected
            $lines = [
                [
                    'account_id'       => $cashAccId,
                    'debit'            => $variance,
                    'credit'           => 0,
                    'line_description' => 'فائض نقدي — وردية #' . $shift->id,
                ],
                [
                    'account_id'       => $this->account($overageCode)->id,
                    'debit'            => 0,
                    'credit'           => $variance,
                    'line_description' => 'تسوية فائض نقدي — وردية #' . $shift->id,
                ],
            ];
        }

        return $this->buildEntry([
            'entry_date'  => $shift->closed_at->toDateString(),
            'reference'   => 'SHIFT-' . $shift->id,
            'source_type' => \App\Models\CashShift::class,
            'source_id'   => $shift->id,
            'branch_id'   => $branchId,
            'description' => 'إقفال وردية نقدية #' . $shift->id
                             . ' — ' . ($shift->user?->name ?? '')
                             . ' / ' . $this->varianceDirection($variance),
        ], $lines);
    }

    /**
     * مخصصات نهاية الخدمة — EOSB Provision (monthly batch)
     *
     * Consolidated entry for all employees in one month:
     *   DR مصروف نهاية الخدمة (6300) = total provision
     *   CR مخصص نهاية الخدمة (2200)  = total provision
     *
     * @param  array<array{employee_id,name,amount,branch_id}> $lines
     */
    public function postEosbProvision(int $year, int $month, float $total, array $lines, ?int $branchId = null): JournalEntry
    {
        $expCode = Setting::get('account_eosb_expense_code',    '6300');
        $provCode = Setting::get('account_eosb_provision_code', '2200');

        $monthNames = [1=>'يناير',2=>'فبراير',3=>'مارس',4=>'أبريل',
                       5=>'مايو',6=>'يونيو',7=>'يوليو',8=>'أغسطس',
                       9=>'سبتمبر',10=>'أكتوبر',11=>'نوفمبر',12=>'ديسمبر'];
        $periodLabel = ($monthNames[$month] ?? $month) . ' ' . $year;

        $entryLines = [
            [
                'account_id'       => $this->account($expCode)->id,
                'debit'            => round($total, 2),
                'credit'           => 0,
                'line_description' => 'مصروف نهاية خدمة — ' . $periodLabel,
            ],
            [
                'account_id'       => $this->account($provCode)->id,
                'debit'            => 0,
                'credit'           => round($total, 2),
                'line_description' => 'مخصص نهاية خدمة — ' . $periodLabel . ' (' . count($lines) . ' موظف)',
            ],
        ];

        return $this->buildEntry([
            'entry_date'  => \Carbon\Carbon::create($year, $month, 1)->endOfMonth()->toDateString(),
            'reference'   => 'EOSB-' . $year . str_pad($month, 2, '0', STR_PAD_LEFT),
            'source_type' => \App\Models\EosbProvision::class,
            'source_id'   => 0,
            'branch_id'   => $branchId ?? $this->resolveBranchId(),
            'description' => 'مخصص نهاية الخدمة الشهري — ' . $periodLabel,
        ], $entryLines);
    }

    /**
     * تحويل بيني بين فرعين — Inter-Branch Cash Transfer
     *
     * Creates TWO journal entries — one per branch — following the Due-From/Due-To pattern
     * used by SAP (company code intercompany clearing) and Oracle (intercompany accounting).
     *
     * Branch A (sender):   DR مستحق من الفرع B (1700.B)   = amount
     *                      CR صندوق الفرع A (1000.A)       = amount
     *
     * Branch B (receiver): DR صندوق الفرع B (1000.B)       = amount
     *                      CR مستحق للفرع A (2700.A)       = amount
     *
     * The two entries are linked via the inter_branch_transfers record.
     *
     * @return array{from: JournalEntry, to: JournalEntry}
     */
    public function postInterBranchTransfer(
        \App\Models\InterBranchTransfer $transfer
    ): array {
        $amount      = round((float) $transfer->amount, 2);
        $fromId      = (int) $transfer->from_branch_id;
        $toId        = (int) $transfer->to_branch_id;
        $dateStr     = $transfer->transfer_date->toDateString();
        $ref         = $transfer->reference ?? 'IBT-' . $transfer->id;
        $desc        = $transfer->description ?? '';

        // ── Branch A (sender) entry ──────────────────────────────────────────
        // DR Due-From Branch B  /  CR Cash Branch A
        $fromEntry = $this->buildEntry([
            'entry_date'  => $dateStr,
            'reference'   => $ref,
            'source_type' => \App\Models\InterBranchTransfer::class,
            'source_id'   => $transfer->id,
            'branch_id'   => $fromId,
            'description' => 'تحويل صادر إلى ' . ($transfer->toBranch?->name ?? "فرع #{$toId}")
                             . ($desc ? ' — ' . $desc : ''),
        ], [
            [
                'account_id'       => \App\Services\BranchAccountingService::dueFromAccountId($toId),
                'debit'            => $amount,
                'credit'           => 0,
                'line_description' => 'مستحق من فرع ' . ($transfer->toBranch?->name ?? $toId),
            ],
            [
                'account_id'       => $this->cashId($fromId),
                'debit'            => 0,
                'credit'           => $amount,
                'line_description' => 'صرف تحويل من صندوق ' . ($transfer->fromBranch?->name ?? $fromId),
            ],
        ]);

        // ── Branch B (receiver) entry ────────────────────────────────────────
        // DR Cash Branch B  /  CR Due-To Branch A
        $toEntry = $this->buildEntry([
            'entry_date'  => $dateStr,
            'reference'   => $ref,
            'source_type' => \App\Models\InterBranchTransfer::class,
            'source_id'   => $transfer->id,
            'branch_id'   => $toId,
            'description' => 'تحويل وارد من ' . ($transfer->fromBranch?->name ?? "فرع #{$fromId}")
                             . ($desc ? ' — ' . $desc : ''),
        ], [
            [
                'account_id'       => $this->cashId($toId),
                'debit'            => $amount,
                'credit'           => 0,
                'line_description' => 'استلام تحويل في صندوق ' . ($transfer->toBranch?->name ?? $toId),
            ],
            [
                'account_id'       => \App\Services\BranchAccountingService::dueToAccountId($fromId),
                'debit'            => 0,
                'credit'           => $amount,
                'line_description' => 'مستحق لفرع ' . ($transfer->fromBranch?->name ?? $fromId),
            ],
        ]);

        return ['from' => $fromEntry, 'to' => $toEntry];
    }

    /**
     * تصنيع/تجميع — Assembly Posting
     *
     * الإنتاج يُقيَّم بتكلفة المكونات المستهلكة (IAS 2 — تكلفة التحويل المباشرة):
     *   DR مخزون (الصنف المنتَج)  = إجمالي تكلفة المكونات
     *   CR مخزون (كل مكوّن)       = كمية المكوّن × تكلفته
     *
     * تُستخدم حسابات المخزون الخاصة بكل صنف إن وُجدت، وإلا الحساب العام (1300).
     */
    public function postAssembly(\App\Models\Assembly $assembly): JournalEntry
    {
        if ($assembly->is_posted) {
            throw new RuntimeException("مستند التصنيع [{$assembly->number}] مُرحَّل مسبقاً");
        }

        $assembly->loadMissing('items.component', 'product');

        $inventoryCode = Setting::get('account_inventory_code', '1300');
        $defaultInvId  = $this->account($inventoryCode)->id;

        $finishedInvId = $assembly->product->inventory_account_id ?: $defaultInvId;
        $totalCost     = round((float) $assembly->total_cost, 2);

        $lines = [[
            'account_id'       => $finishedInvId,
            'debit'            => $totalCost,
            'credit'           => 0,
            'line_description' => 'إنتاج ' . $assembly->product->name
                                  . ' × ' . rtrim(rtrim(number_format($assembly->quantity, 3), '0'), '.'),
        ]];

        // اعتمادات المكونات — مع تسوية فروق التقريب على آخر سطر
        $creditSum = 0.0;
        $itemCount = $assembly->items->count();
        foreach ($assembly->items as $idx => $item) {
            $credit = ($idx === $itemCount - 1)
                ? round($totalCost - $creditSum, 2)              // آخر سطر يمتص فرق التقريب
                : round((float) $item->total_cost, 2);
            $creditSum += $credit;

            $lines[] = [
                'account_id'       => $item->component->inventory_account_id ?: $defaultInvId,
                'debit'            => 0,
                'credit'           => $credit,
                'line_description' => 'استهلاك ' . $item->component->name
                                      . ' × ' . rtrim(rtrim(number_format($item->quantity, 4), '0'), '.'),
            ];
        }

        $entry = $this->buildEntry([
            'entry_date'  => $assembly->assembly_date->toDateString(),
            'reference'   => $assembly->number,
            'source_type' => \App\Models\Assembly::class,
            'source_id'   => $assembly->id,
            'branch_id'   => $assembly->warehouse?->branch_id,
            'description' => 'قيد تصنيع — ' . $assembly->number . ' (' . $assembly->product->name . ')',
        ], $lines);

        $assembly->update(['is_posted' => true]);

        return $entry;
    }

    private function varianceDirection(float $v): string
    {
        if ($v > 0) return 'فائض ' . number_format($v, 2);
        return 'عجز '  . number_format(abs($v), 2);
    }

    /** Helper: Arabic month name */
    private function monthName(int $month): string
    {
        return [1=>'يناير',2=>'فبراير',3=>'مارس',4=>'أبريل',
                5=>'مايو',6=>'يونيو',7=>'يوليو',8=>'أغسطس',
                9=>'سبتمبر',10=>'أكتوبر',11=>'نوفمبر',12=>'ديسمبر'][$month] ?? (string)$month;
    }

    /**
     * Step 10 — Year-end closing entry.
     *
     * Zeroes all revenue & expense balances for $year into Retained Earnings (3100).
     *   Revenue accounts (credit normal): DR Revenue / CR Retained Earnings
     *   Expense accounts (debit normal):  DR Retained Earnings / CR Expense
     */
    public function postYearEndClosing(int $year): JournalEntry
    {
        $reCode    = Setting::get('account_retained_earnings_code', '3100');
        $reAccount = $this->account($reCode);

        $service = new FinancialStatementService();
        $from    = Carbon::create($year, 1,  1)->startOfDay();
        $to      = Carbon::create($year, 12, 31)->endOfDay();

        $accounts = Account::where('is_active', true)
            ->whereIn('type', ['revenue', 'expense'])
            ->orderBy('code')
            ->get();

        $lines        = [];
        $totalRevenue = 0.0;
        $totalExpense = 0.0;

        foreach ($accounts as $account) {
            $balance = round($service->getPeriodBalance($account, $from, $to), 2);
            if (abs($balance) < 0.005) {
                continue;
            }

            if ($account->type === 'revenue') {
                // Revenue normal balance is credit; close by debiting.
                // If balance is negative (contra-revenue like sales discounts with net debit),
                // reverse direction to keep journal line amounts positive.
                if ($balance > 0) {
                    $lines[] = [
                        'account_id'       => $account->id,
                        'debit'            => $balance,
                        'credit'           => 0,
                        'line_description' => 'إقفال إيراد – ' . $account->name,
                    ];
                } else {
                    $lines[] = [
                        'account_id'       => $account->id,
                        'debit'            => 0,
                        'credit'           => abs($balance),
                        'line_description' => 'إقفال إيراد (رصيد عكسي) – ' . $account->name,
                    ];
                }
                $totalRevenue += $balance;
            } else {
                // Expense normal balance is debit; close by crediting.
                // If balance is negative (reversed expense), reverse direction.
                if ($balance > 0) {
                    $lines[] = [
                        'account_id'       => $account->id,
                        'debit'            => 0,
                        'credit'           => $balance,
                        'line_description' => 'إقفال مصروف – ' . $account->name,
                    ];
                } else {
                    $lines[] = [
                        'account_id'       => $account->id,
                        'debit'            => abs($balance),
                        'credit'           => 0,
                        'line_description' => 'إقفال مصروف (رصيد عكسي) – ' . $account->name,
                    ];
                }
                $totalExpense += $balance;
            }
        }

        if (empty($lines)) {
            throw new RuntimeException("لا توجد أرصدة إيراد أو مصروف للإقفال في سنة {$year}");
        }

        $netIncome = round($totalRevenue - $totalExpense, 2);

        // Plug the net result into Retained Earnings
        if ($netIncome > 0) {
            $lines[] = [
                'account_id'       => $reAccount->id,
                'debit'            => 0,
                'credit'           => $netIncome,
                'line_description' => "إقفال صافي الربح لسنة {$year}",
            ];
        } elseif ($netIncome < 0) {
            $lines[] = [
                'account_id'       => $reAccount->id,
                'debit'            => abs($netIncome),
                'credit'           => 0,
                'line_description' => "إقفال صافي الخسارة لسنة {$year}",
            ];
        }

        return $this->buildEntry([
            'entry_date'  => Carbon::create($year, 12, 31),
            'reference'   => "CLOSE-{$year}",
            'source_type' => null,
            'source_id'   => null,
            'description' => "قيد الإقفال السنوي – {$year}",
        ], $lines);
    }
}
