<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Customer;
use App\Models\ServiceInvoice;
use App\Models\Setting;
use App\Services\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * فواتير إيراد الخدمات (Service Revenue Invoices — IFRS 15).
 * تثبت إيراد خدمة + ضريبة مخرجات + ذمة/تحصيل العميل، وتُرحَّل تلقائياً لدفتر الأستاذ،
 * وتُلتقط في كشف الإيرادات والمصروفات بعضوية حصرية.
 */
class ServiceInvoiceController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:services.view')->only(['index', 'show']);
        $this->middleware('can:services.manage')->only(['create', 'store']);
    }

    public function index()
    {
        $invoices = ServiceInvoice::with('customer', 'serviceAccount', 'user')
            ->orderByDesc('invoice_date')->orderByDesc('id')->get();
        $currency = Setting::get('currency_symbol', 'ج.م');

        return view('services.index', compact('invoices', 'currency'));
    }

    public function create()
    {
        $customers       = Customer::orderBy('name')->get(['id', 'name']);
        $revenueAccounts = Account::where('type', 'revenue')->orderBy('code')->get(['id', 'code', 'name']);
        $currency        = Setting::get('currency_symbol', 'ج.م');
        $defaultService  = Setting::get('account_service_revenue_code', '4400');

        return view('services.create', compact('customers', 'revenueAccounts', 'currency', 'defaultService'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'invoice_date'       => 'required|date',
            'customer_id'        => 'nullable|exists:customers,id',
            'customer_name'      => 'nullable|string|max:255',
            'service_account_id' => 'nullable|exists:accounts,id',
            'description'        => 'nullable|string|max:500',
            'total_amount'       => 'required|numeric|min:0.01',
            'tax_amount'         => 'nullable|numeric|min:0',
            'is_credit'          => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            $invoice = ServiceInvoice::create([
                'invoice_date'       => $data['invoice_date'],
                'customer_id'        => $data['customer_id'] ?? null,
                'customer_name'      => $data['customer_name'] ?? null,
                'service_account_id' => $data['service_account_id'] ?? null,
                'description'        => $data['description'] ?? null,
                'total_amount'       => round((float) $data['total_amount'], 2),
                'tax_amount'         => round((float) ($data['tax_amount'] ?? 0), 2),
                'is_credit'          => (bool) ($data['is_credit'] ?? false),
                'user_id'            => auth()->id(),
                'branch_id'          => auth()->user()->branch_id ?? Setting::get('default_branch_id'),
            ]);

            app(LedgerPostingService::class)->postServiceInvoice($invoice);

            DB::commit();

            return redirect()->route('service-invoices.show', $invoice)
                ->with('success', 'تم تسجيل فاتورة الخدمات وترحيلها — ' . $invoice->fresh()->invoice_number);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'خطأ: ' . $e->getMessage());
        }
    }

    public function show(ServiceInvoice $service_invoice)
    {
        $service_invoice->load('customer', 'serviceAccount', 'user', 'journalEntry', 'resStatement');
        $currency = Setting::get('currency_symbol', 'ج.م');

        return view('services.show', compact('service_invoice', 'currency'));
    }
}
