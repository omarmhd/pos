<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerDeposit;
use App\Models\Setting;
use App\Services\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerDepositController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:customers.payments');
    }

    public function create(Customer $customer)
    {
        $currency = Setting::get('currency_symbol', 'ج.م');
        $balance  = $customer->depositBalance();
        return view('customers.deposits.create', compact('customer', 'currency', 'balance'));
    }

    public function store(Request $request, Customer $customer)
    {
        $request->validate([
            'type'           => 'required|in:deposit,refund',
            'voucher_date'   => 'required|date',
            'amount'         => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,bank,mobile_wallet',
            'reference'      => 'nullable|string|max:255',
            'notes'          => 'nullable|string',
        ]);

        // Validate refund doesn't exceed available balance
        if ($request->type === 'refund') {
            $balance = $customer->depositBalance();
            if ($request->amount > $balance + 0.005) {
                return back()->withInput()
                    ->with('error', 'مبلغ الاسترداد (' . number_format($request->amount, 2) . ') يتجاوز الرصيد المتاح (' . number_format($balance, 2) . ')');
            }
        }

        DB::beginTransaction();
        try {
            $deposit = CustomerDeposit::create([
                'voucher_date'   => $request->voucher_date,
                'customer_id'    => $customer->id,
                'type'           => $request->type,
                'amount'         => $request->amount,
                'payment_method' => $request->payment_method,
                'reference'      => $request->reference,
                'notes'          => $request->notes,
                'user_id'        => auth()->id(),
                'branch_id'      => auth()->user()->branch_id
                                    ?? \App\Models\Setting::get('default_branch_id'),
            ]);

            (new LedgerPostingService())->postCustomerDeposit($deposit);

            DB::commit();

            $label = $request->type === 'deposit' ? 'الإيداع' : 'الاسترداد';
            return redirect()->route('customers.show', $customer)
                ->with('success', "تم تسجيل {$label} بنجاح — {$deposit->voucher_number}");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'خطأ: ' . $e->getMessage());
        }
    }
}
