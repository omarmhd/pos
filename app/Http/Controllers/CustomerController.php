<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Sale;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class CustomerController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:customers.view')->only(['index', 'show']);
        $this->middleware('can:customers.create')->only(['create', 'store']);
        $this->middleware('can:customers.edit')->only(['edit', 'update']);
        $this->middleware('can:customers.delete')->only(['destroy']);
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Customer::select('customers.*')
                ->selectSub(
                    Sale::selectRaw('COUNT(*)')
                        ->whereColumn('customer_id', 'customers.id')
                        ->where('is_credit', true),
                    'credit_sales_count'
                )
                ->selectSub(
                    Sale::selectRaw('COALESCE(SUM(total_amount), 0)')
                        ->whereColumn('customer_id', 'customers.id')
                        ->where('is_credit', true),
                    'total_billed'
                )
                ->selectSub(
                    CustomerPayment::selectRaw('COALESCE(SUM(amount), 0)')
                        ->whereColumn('customer_id', 'customers.id'),
                    'total_paid'
                );

            return DataTables::eloquent($query)
                ->addColumn('outstanding', fn($c) => number_format(max(0, (float)$c->total_billed - (float)$c->total_paid), 2))
                ->addColumn('credit_limit_fmt', fn($c) => (float)$c->credit_limit > 0 ? number_format($c->credit_limit, 2) : '—')
                ->addColumn('status_badge', fn($c) => $c->is_active
                    ? "<span class='badge bg-success'>نشط</span>"
                    : "<span class='badge bg-secondary'>موقوف</span>")
                ->addColumn('action', fn($c) => $this->actionButtons($c))
                ->rawColumns(['status_badge', 'action'])
                ->make(true);
        }

        return view('customers.index');
    }

    public function create()
    {
        return view('customers.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'phone'         => 'nullable|string|max:30',
            'email'         => 'nullable|email|max:255',
            'address'       => 'nullable|string',
            'credit_limit'  => 'nullable|numeric|min:0',
            'notes'         => 'nullable|string',
            'price_list_id' => 'nullable|exists:price_lists,id',
        ]);

        $data['credit_limit']  = $data['credit_limit'] ?? 0;
        $data['price_list_id'] = $data['price_list_id'] ?: null;
        Customer::create($data);

        return redirect()->route('customers.index')->with('success', 'تم إضافة العميل بنجاح');
    }

    public function show(Customer $customer)
    {
        $creditSales = Sale::with('customerPayments')
            ->where('customer_id', $customer->id)
            ->where('is_credit', true)
            ->latest()
            ->get()
            ->map(function ($sale) {
                $sale->outstanding = $sale->outstandingBalance();
                return $sale;
            });

        $payments = $customer->payments()->with('sale', 'user')->latest('received_at')->get();

        $totalBilled     = $customer->totalCreditBilled();
        $totalPaid       = $customer->totalPaid();
        $outstanding     = max(0, $totalBilled - $totalPaid);
        $availableCredit = max(0, (float) $customer->credit_limit - $outstanding);

        // Deposit balance
        $depositBalance  = $customer->depositBalance();
        $depositHistory  = $customer->deposits()->with('user')->latest('voucher_date')->get();
        $currency        = \App\Models\Setting::get('currency_symbol', 'ج.م');

        return view('customers.show', compact(
            'customer', 'creditSales', 'payments',
            'totalBilled', 'totalPaid', 'outstanding', 'availableCredit',
            'depositBalance', 'depositHistory', 'currency'
        ));
    }

    public function edit(Customer $customer)
    {
        return view('customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'phone'         => 'nullable|string|max:30',
            'email'         => 'nullable|email|max:255',
            'address'       => 'nullable|string',
            'credit_limit'  => 'nullable|numeric|min:0',
            'notes'         => 'nullable|string',
            'is_active'     => 'nullable|boolean',
            'price_list_id' => 'nullable|exists:price_lists,id',
        ]);

        $data['is_active']     = $request->boolean('is_active');
        $data['price_list_id'] = $data['price_list_id'] ?: null;
        $customer->update($data);

        return redirect()->route('customers.show', $customer)->with('success', 'تم تحديث بيانات العميل');
    }

    public function destroy(Customer $customer)
    {
        if ($customer->outstandingBalance() > 0) {
            return back()->with('error', 'لا يمكن حذف العميل لوجود رصيد مستحق غير مسدد');
        }

        $customer->delete();
        return redirect()->route('customers.index')->with('success', 'تم حذف العميل');
    }

    // ── private helpers ──────────────────────────────────────────────────────

    private function actionButtons(Customer $c): string
    {
        $show = '<a href="'.route('customers.show', $c).'" class="btn btn-sm btn-info btn-action" title="عرض"><i class="bi bi-eye"></i></a>';
        $edit = '<a href="'.route('customers.edit', $c).'" class="btn btn-sm btn-primary btn-action" title="تعديل"><i class="bi bi-pencil"></i></a>';
        return '<div class="d-flex gap-1 flex-nowrap">'.$show.$edit.'</div>';
    }
}
