<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Sale;
use App\Services\LedgerPostingService;
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

            // DR Cash/Bank → CR AR  (via shared service for consistency)
            (new LedgerPostingService())->postCustomerPayment(
                $payment->load('customer', 'sale')
            );
        });

        return redirect()->route('customers.show', $customer)
            ->with('success', 'تم تسجيل الدفعة وترحيل القيد المحاسبي بنجاح');
    }
}
