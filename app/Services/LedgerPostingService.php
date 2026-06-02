<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\CustomerDeposit;
use App\Models\EmployeeLoan;
use App\Models\PaymentVoucher;
use App\Models\Purchase;
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
        $user = auth()->user();
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
     * Validate balance then persist a JournalEntry with its lines.
     *
     * @param  array<string, mixed>                                                       $header
     * @param  list<array{account_id:int,debit:float,credit:float,line_description:string}> $lines
     */
    private function buildEntry(array $header, array $lines): JournalEntry
    {
        $debits  = round(array_sum(array_column($lines, 'debit')),  2);
        $credits = round(array_sum(array_column($lines, 'credit')), 2);

        if (abs($debits - $credits) > 0.005) {
            throw new RuntimeException(
                "القيد غير متوازن — مجموع المدين: {$debits} / مجموع الدائن: {$credits}"
            );
        }

        $entry = JournalEntry::create(array_merge($header, [
            'user_id'   => auth()->id() ?? 1,
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
        $cogs     = round(
            $sale->items->sum(fn($i) => $i->quantity * (float) $i->cost_price),
            2
        );

        $cashCode      = Setting::get('account_cash_code',        '1000');
        $bankCode      = Setting::get('account_bank_code',        '1100');
        $arCode        = Setting::get('account_ar_code',          '1200');
        $inventoryCode = Setting::get('account_inventory_code',   '1300');
        $taxCode       = Setting::get('account_tax_payable_code', '2200');
        $salesCode     = Setting::get('account_sales_code',       '4000');
        $discountCode  = Setting::get('account_discount_code',    '4300');
        $cogsCode      = Setting::get('account_cogs_code',        '5000');

        $balanceUsed     = round((float) ($sale->balance_used ?? 0), 2);
        $depositsCode    = Setting::get('account_customer_deposits_code', '2050');

        $lines = [];

        // ── Debit: Cash/Bank, AR, or Customer Deposits ──
        if ($sale->is_credit) {
            $lines[] = [
                'account_id'       => $this->account($arCode)->id,
                'debit'            => $total,
                'credit'           => 0,
                'line_description' => 'ذمم عميل – ' . ($sale->customer?->name ?? 'عميل'),
            ];
        } else {
            // Part paid from deposit balance
            if ($balanceUsed > 0) {
                $lines[] = [
                    'account_id'       => $this->account($depositsCode)->id,
                    'debit'            => $balanceUsed,
                    'credit'           => 0,
                    'line_description' => 'خصم رصيد إيداع – ' . ($sale->customer?->name ?? 'عميل'),
                ];
            }
            // Remaining paid in cash/card (nothing if fully from deposit)
            $cashPaid = round($total - $balanceUsed, 2);
            if ($cashPaid > 0 && $sale->payment_method !== 'deposit_balance') {
                $drCode = $sale->payment_method === 'card' ? $bankCode : $cashCode;
                $label  = match ($sale->payment_method) {
                    'card'          => 'تحصيل بطاقة',
                    'mobile_wallet' => 'تحصيل محفظة',
                    default         => 'تحصيل نقدي',
                };
                $lines[] = [
                    'account_id'       => $this->account($drCode)->id,
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

        $salesReturnsCode = Setting::get('account_sales_returns_code', '4200');
        $cashCode         = Setting::get('account_cash_code',          '1000');
        $bankCode         = Setting::get('account_bank_code',          '1100');
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
            $crCode = $ret->refund_method === 'bank' ? $bankCode : $cashCode;
            $label  = $ret->refund_method === 'bank' ? 'صرف استرداد بنكي' : 'صرف استرداد نقدي';
            $lines[] = [
                'account_id'       => $this->account($crCode)->id,
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

        $total        = round((float) $ret->total_amount, 2);
        $apCode       = Setting::get('account_ap_code',        '2000');
        $cashCode     = Setting::get('account_cash_code',      '1000');
        $bankCode     = Setting::get('account_bank_code',      '1100');
        $inventoryCode = Setting::get('account_inventory_code', '1300');
        $supplierName = $ret->supplier?->name ?? 'مورد';

        // Debit side: what the supplier owes us (AP reduction or cash/bank inflow)
        $drCode = match($ret->refund_method) {
            'cash'         => $cashCode,
            'bank'         => $bankCode,
            default        => $apCode,   // ap_deduction
        };
        $drLabel = match($ret->refund_method) {
            'cash'         => 'استرداد نقدي من مورد',
            'bank'         => 'استرداد بنكي من مورد',
            default        => 'تخفيض ذمة مورد – ' . $supplierName,
        };

        $lines = [
            [
                'account_id'       => $this->account($drCode)->id,
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
        $paid   = round((float) $purchase->paid_amount,  2);
        $unpaid = round($total - $paid, 2);

        $cashCode      = Setting::get('account_cash_code',      '1000');
        $apCode        = Setting::get('account_ap_code',        '2000');
        $inventoryCode = Setting::get('account_inventory_code', '1300');

        $supplierName = $purchase->supplier?->name ?? 'مورد';
        $lines = [];

        // ── Debit: Inventory (full invoice amount) ──
        $lines[] = [
            'account_id'       => $this->account($inventoryCode)->id,
            'debit'            => $total,
            'credit'           => 0,
            'line_description' => 'مشتريات بضاعة – ' . $purchase->invoice_number,
        ];

        // ── Credit: AP for unpaid portion ──
        if ($unpaid > 0) {
            $lines[] = [
                'account_id'       => $this->account($apCode)->id,
                'debit'            => 0,
                'credit'           => $unpaid,
                'line_description' => 'ذمة مورد – ' . $supplierName,
            ];
        }

        // ── Credit: Cash for any immediate payment ──
        if ($paid > 0) {
            $lines[] = [
                'account_id'       => $this->account($cashCode)->id,
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

        $amount = round((float) $payment->amount, 2);

        $apCode   = Setting::get('account_ap_code',   '2000');
        $cashCode = Setting::get('account_cash_code', '1000');
        $bankCode = Setting::get('account_bank_code', '1100');

        $crCode       = $payment->payment_method === 'card' ? $bankCode : $cashCode;
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
                'account_id'       => $this->account($crCode)->id,
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

        $amount = round((float) $payment->amount, 2);

        $arCode   = Setting::get('account_ar_code',   '1200');
        $cashCode = Setting::get('account_cash_code', '1000');
        $bankCode = Setting::get('account_bank_code', '1100');

        $drCode       = $payment->payment_method === 'card' ? $bankCode : $cashCode;
        $customerName = $payment->customer?->name ?? 'عميل';
        $ref          = $payment->sale?->invoice_number ?? ('CPM-' . $payment->id);

        $lines = [
            [
                'account_id'       => $this->account($drCode)->id,
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

        $amount       = round((float) $deposit->amount, 2);
        $cashCode     = Setting::get('account_cash_code', '1000');
        $bankCode     = Setting::get('account_bank_code', '1100');
        $depositCode  = Setting::get('account_customer_deposits_code', '2050');

        $cashOrBankCode = $deposit->payment_method === 'bank' ? $bankCode : $cashCode;
        $customerName   = $deposit->customer?->name ?? 'عميل';
        $typeLabel      = $deposit->type === 'deposit' ? 'إيداع' : 'استرداد';

        if ($deposit->type === 'deposit') {
            $lines = [
                [
                    'account_id'       => $this->account($cashOrBankCode)->id,
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
                    'account_id'       => $this->account($cashOrBankCode)->id,
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
            'branch_id'   => $this->resolveBranchId(),
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
     * سند قبض — Receipt Voucher
     *
     * DR cash_account (صندوق/بنك) = amount
     * CR account      (الحساب المقابل) = amount
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

        $amount = round((float) $voucher->amount, 2);

        $lines = [
            [
                'account_id'       => $voucher->cash_account_id,
                'debit'            => $amount,
                'credit'           => 0,
                'line_description' => 'تحصيل من ' . $voucher->received_from,
            ],
            [
                'account_id'       => $voucher->account_id,
                'debit'            => 0,
                'credit'           => $amount,
                'line_description' => 'سند قبض – ' . $voucher->voucher_number,
            ],
        ];

        $entry = $this->buildEntry([
            'entry_date'  => Carbon::parse($voucher->voucher_date),
            'reference'   => $voucher->voucher_number,
            'source_type' => ReceiptVoucher::class,
            'source_id'   => $voucher->id,
            'branch_id'   => $this->resolveBranchId(),
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
            'branch_id'   => $this->resolveBranchId(),
            'description' => 'سند صرف – ' . $voucher->voucher_number . ' / ' . $voucher->paid_to,
        ], $lines);

        $voucher->update(['is_posted' => true, 'journal_entry_id' => $entry->id]);

        return $entry;
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
