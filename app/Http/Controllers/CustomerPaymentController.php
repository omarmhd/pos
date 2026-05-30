<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Sale;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerPaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:customers.payments');
    }

    public function create(Customer $customer, Request $request)
    {
        $saleId = $request->input('sale_id');

        // Only show unpaid/partially-paid credit invoices for this customer
        $unpaidSales = Sale::where('customer_id', $customer->id)
            ->where('is_credit', true)
            ->get()
            ->map(function ($sale) {
                $sale->outstanding = $sale->outstandingBalance();
                return $sale;
            })
            ->filter(fn($s) => $s->outstanding > 0)
            ->values();

        $selectedSale = $saleId ? $unpaidSales->firstWhere('id', (int) $saleId) : null;

        return view('customer_payments.create', compact('customer', 'unpaidSales', 'selectedSale'));
    }

    public function store(Customer $customer, Request $request)
    {
        $data = $request->validate([
            'sale_id'        => 'required|exists:sales,id',
            'amount'         => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,card,mobile_wallet',
            'received_at'    => 'required|date',
            'notes'          => 'nullable|string',
        ]);

        $sale = Sale::where('customer_id', $customer->id)
            ->where('is_credit', true)
            ->findOrFail($data['sale_id']);

        $outstanding = $sale->outstandingBalance();

        if ($data['amount'] > $outstanding + 0.01) {
            return back()->with('error', 'المبلغ المدفوع أكبر من الرصيد المستحق (' . number_format($outstanding, 2) . ')');
        }

        DB::transaction(function () use ($customer, $sale, $data) {
            $payment = CustomerPayment::create([
                'customer_id'    => $customer->id,
                'sale_id'        => $sale->id,
                'amount'         => $data['amount'],
                'payment_method' => $data['payment_method'],
                'received_at'    => $data['received_at'],
                'notes'          => $data['notes'] ?? null,
                'user_id'        => auth()->id(),
            ]);

            // Post GL entry: DR Cash/Bank → CR Accounts Receivable
            $cashCode = Setting::get('account_cash_code', '1000');
            $bankCode = Setting::get('account_bank_code', '1100');
            $arCode   = Setting::get('account_ar_code',   '1200');

            $drCode = $data['payment_method'] === 'card' ? $bankCode : $cashCode;

            $resolveAccount = function (string $code) {
                $account = Account::where('code', $code)
                    ->where('is_active', true)
                    ->where('is_header', false)
                    ->first();
                if (!$account) {
                    throw new \RuntimeException("حساب الذمم غير موجود أو غير نشط: كود [{$code}]");
                }
                return $account;
            };

            $debitAccount  = $resolveAccount($drCode);
            $creditAccount = $resolveAccount($arCode);
            $amount        = round((float) $data['amount'], 2);

            $entry = JournalEntry::create([
                'entry_date'  => Carbon::parse($data['received_at']),
                'reference'   => $sale->invoice_number,
                'source_type' => CustomerPayment::class,
                'source_id'   => $payment->id,
                'description' => 'تحصيل من عميل ' . $customer->name . ' مقابل فاتورة ' . $sale->invoice_number,
                'user_id'     => auth()->id(),
                'posted_at'   => now(),
            ]);

            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => $debitAccount->id,
                'debit'            => $amount,
                'credit'           => 0,
                'line_description' => 'تحصيل من ' . $customer->name,
            ]);

            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => $creditAccount->id,
                'debit'            => 0,
                'credit'           => $amount,
                'line_description' => 'تسوية ذمم ' . $customer->name . ' – ' . $sale->invoice_number,
            ]);
        });

        return redirect()->route('customers.show', $customer)
            ->with('success', 'تم تسجيل الدفعة وترحيل القيد المحاسبي بنجاح');
    }
}
