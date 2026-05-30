<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\SupplierPayment;
use Carbon\Carbon;
use RuntimeException;

class LedgerPostingService
{
    /** Per-request account code cache to avoid repeated lookups */
    private array $cache = [];

    // ── internal helpers ─────────────────────────────────────────────────────

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

        $lines = [];

        // ── Debit: Cash/Bank or Accounts Receivable ──
        if ($sale->is_credit) {
            $lines[] = [
                'account_id'       => $this->account($arCode)->id,
                'debit'            => $total,
                'credit'           => 0,
                'line_description' => 'ذمم عميل – ' . ($sale->customer?->name ?? 'عميل'),
            ];
        } else {
            $drCode  = $sale->payment_method === 'card' ? $bankCode : $cashCode;
            $label   = match ($sale->payment_method) {
                'card'          => 'تحصيل بطاقة',
                'mobile_wallet' => 'تحصيل محفظة',
                default         => 'تحصيل نقدي',
            };
            $lines[] = [
                'account_id'       => $this->account($drCode)->id,
                'debit'            => $total,
                'credit'           => 0,
                'line_description' => $label,
            ];
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
            'description' => 'قيد مبيعات – ' . $sale->invoice_number,
        ], $lines);

        $sale->update(['is_posted' => true]);

        return $entry;
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
            'description' => 'تحصيل دفعة – ' . $customerName
                             . ($payment->sale ? ' / فاتورة ' . $ref : ''),
        ], $lines);
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
