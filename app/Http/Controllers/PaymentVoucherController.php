<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CustomerDeposit;
use App\Models\PaymentVoucher;
use App\Models\Setting;
use App\Models\Supplier;
use App\Services\BranchAccountingService;
use App\Services\LedgerPostingService;
use App\Services\PdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentVoucherController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:vouchers.view')->only(['index', 'data', 'show', 'pdf']);
        $this->middleware('can:vouchers.create')->only(['create', 'store']);
        $this->middleware('can:vouchers.delete')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $branches     = \App\Models\Branch::where('is_active', true)->orderBy('name')->get(['id','name','code']);
        $branchId     = $this->effectiveBranchId($request);
        $branchLocked = $this->isBranchLocked();
        return view('vouchers.payments.index', compact('branches', 'branchId', 'branchLocked'));
    }

    public function data(Request $request)
    {
        $branchId = $this->effectiveBranchId($request);
        $from     = $request->filled('from') ? $request->from : null;
        $to       = $request->filled('to')   ? $request->to   : null;
        $method   = $request->filled('method') ? $request->method : null;

        try {
            // ── سندات الصرف العامة ──────────────────────────────────────────
            $vouchers = PaymentVoucher::with('user', 'account', 'cashAccount')
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->when($from,     fn($q) => $q->whereDate('voucher_date', '>=', $from))
                ->when($to,       fn($q) => $q->whereDate('voucher_date', '<=', $to))
                ->when($method,   fn($q) => $q->where('payment_method', $method))
                ->orderByDesc('voucher_date')
                ->get()
                ->map(fn($v) => [
                    'voucher_number'   => $v->voucher_number,
                    'date_fmt'         => $v->voucher_date->format('Y-m-d'),
                    'type_badge'       => '<span class="badge bg-secondary">صرف عام</span>',
                    'party'            => e($v->paid_to),
                    'account_name'     => e(($v->account?->code ?? '') . ' — ' . ($v->account?->name ?? '—')),
                    'cash_account_name'=> e($v->cashAccount?->name ?? '—'),
                    'amount_fmt'       => number_format($v->amount, 2),
                    'status'           => $v->is_posted
                        ? '<span class="badge bg-success">مُرحَّل</span>'
                        : '<span class="badge bg-warning text-dark">مسودة</span>',
                    'user_name'        => e($v->user?->name ?? '—'),
                    'action'           => $this->actionButtons($v),
                ])
                ->toBase();

            // ── استرداد إيداعات العملاء ─────────────────────────────────────
            $depositAcctLabel = Account::where('code', Setting::get('account_customer_deposits_code', '2050'))->value('name') ?? 'سُلَف العملاء';
            $cashAcctLabel    = Account::where('code', Setting::get('account_cash_code', '1000'))->value('name')            ?? 'الصندوق';
            $depositCode      = Setting::get('account_customer_deposits_code', '2050');

            $refunds = CustomerDeposit::with('customer', 'user')
                ->where('type', 'refund')
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->when($from,     fn($q) => $q->whereDate('voucher_date', '>=', $from))
                ->when($to,       fn($q) => $q->whereDate('voucher_date', '<=', $to))
                ->when($method,   fn($q) => $q->where('payment_method', $method))
                ->orderByDesc('voucher_date')
                ->get()
                ->map(fn($d) => [
                    'voucher_number'   => $d->voucher_number,
                    'date_fmt'         => $d->voucher_date->format('Y-m-d'),
                    'type_badge'       => '<span class="badge bg-warning text-dark">استرداد إيداع</span>',
                    'party'            => e($d->customer?->name ?? '—'),
                    'account_name'     => e($depositCode . ' — ' . $depositAcctLabel),
                    'cash_account_name'=> e($cashAcctLabel),
                    'amount_fmt'       => number_format($d->amount, 2),
                    'status'           => $d->is_posted
                        ? '<span class="badge bg-success">مُرحَّل</span>'
                        : '<span class="badge bg-warning text-dark">مسودة</span>',
                    'user_name'        => e($d->user?->name ?? '—'),
                    'action'           => $d->customer_id
                        ? '<a href="' . route('customers.show', $d->customer_id) . '" class="btn btn-sm btn-info btn-action" title="ملف العميل"><i class="bi bi-person"></i></a>'
                        : '—',
                ])
                ->toBase();

            $rows = $vouchers->merge($refunds)
                ->sortByDesc('date_fmt')
                ->values()
                ->all();

            return response()->json(['data' => $rows]);

        } catch (\Throwable $e) {
            return response()->json(['data' => [], 'error' => $e->getMessage()]);
        }
    }

    public function create()
    {
        $accounts  = Account::where('is_active', true)->where('is_header', false)->orderBy('code')->get();
        $suppliers = Supplier::orderBy('name')->get(['id', 'name']);
        $currency  = Setting::get('currency_symbol', 'ج.م');
        $branches  = \App\Models\Branch::where('is_active', true)->orderBy('name')->get(['id','name','code']);

        $cashCode = Setting::get('account_cash_code', '1000');
        $bankCode = Setting::get('account_bank_code', '1100');

        // Pre-select branch-specific cash account (1000.XX) for branch users.
        $userBranchId = auth()->user()?->branch_id;
        if ($userBranchId) {
            $defaultCashAccount = Account::find(
                BranchAccountingService::cashAccountId($userBranchId)
            );
        } else {
            $defaultCashAccount = Account::where('code', $cashCode)->where('is_active', true)->first();
        }

        $branchId     = $this->effectiveBranchId(request());
        $branchLocked = $this->isBranchLocked();
        $currencies   = \App\Models\Currency::where('is_active', true)->orderByDesc('is_base')->get();

        return view('vouchers.payments.create', compact(
            'accounts', 'suppliers', 'currency', 'defaultCashAccount',
            'cashCode', 'bankCode', 'branches', 'branchId', 'branchLocked', 'currencies'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'voucher_date'    => 'required|date',
            'second_date'     => 'nullable|date',
            'paid_to'         => 'required|string|max:255',
            'supplier_id'     => 'nullable|exists:suppliers,id',
            'account_id'      => 'required|exists:accounts,id',
            'cash_account_id' => 'required|exists:accounts,id',
            'amount'          => 'required_without:amount_fc|nullable|numeric|min:0.01',
            'currency_id'     => 'nullable|exists:currencies,id',
            'amount_fc'       => 'nullable|numeric|min:0.01',
            'payment_method'  => 'required|in:cash,bank,mobile_wallet',
            'reference'       => 'nullable|string|max:255',
            'notes'           => 'nullable|string',
        ]);

        $account     = Account::findOrFail($request->account_id);
        $cashAccount = Account::findOrFail($request->cash_account_id);

        if ($account->is_header || $cashAccount->is_header) {
            return back()->withErrors(['account_id' => 'لا يمكن استخدام حساب رئيسي (تجميعي)'])->withInput();
        }
        if ($account->id === $cashAccount->id) {
            return back()->withErrors(['account_id' => 'الحساب المدين والدائن لا يمكن أن يكونا نفس الحساب'])->withInput();
        }

        // ── العملة: التحويل للعملة الأساسية بسعر صرف لحظة السند ──────────────
        $currencyId   = null;
        $exchangeRate = 1.0;
        $amountFc     = null;
        $amount       = round((float) $request->amount, 2);

        if ($request->currency_id) {
            $cur = \App\Models\Currency::find($request->currency_id);
            if ($cur && !$cur->is_base) {
                if (!$request->filled('amount_fc')) {
                    return back()->withErrors(['amount_fc' => 'أدخل المبلغ بالعملة المختارة'])->withInput();
                }
                $currencyId   = $cur->id;
                $exchangeRate = (float) $cur->exchange_rate;
                $amountFc     = round((float) $request->amount_fc, 4);
                $amount       = round($amountFc * $exchangeRate, 2);
            }
        }
        if ($amount < 0.01) {
            return back()->withErrors(['amount' => 'المبلغ غير صحيح'])->withInput();
        }

        DB::beginTransaction();
        try {
            // Branch resolution (SAP: Company Code selection):
            // - Locked users (branch employees): always their own branch
            // - Global users (admin/manager): branch from form, fallback to default
            $resolvedBranch = $this->isBranchLocked()
                ? (auth()->user()?->branch_id ?? \App\Models\Setting::get('default_branch_id'))
                : ($request->input('branch_id') ?: auth()->user()?->branch_id ?: \App\Models\Setting::get('default_branch_id'));

            $voucher = PaymentVoucher::create([
                'voucher_date'    => $request->voucher_date,
                'second_date'     => $request->second_date ?: null,
                'paid_to'         => $request->paid_to,
                'supplier_id'     => $request->supplier_id ?: null,
                'account_id'      => $request->account_id,
                'cash_account_id' => $request->cash_account_id,
                'amount'          => $amount,
                'currency_id'     => $currencyId,
                'exchange_rate'   => $exchangeRate,
                'amount_fc'       => $amountFc,
                'payment_method'  => $request->payment_method,
                'reference'       => $request->reference,
                'notes'           => $request->notes,
                'user_id'         => auth()->id(),
                'branch_id'       => $resolvedBranch,
            ]);

            (new LedgerPostingService())->postPaymentVoucher($voucher);

            DB::commit();

            return redirect()->route('vouchers.payments.show', $voucher)
                ->with('success', 'تم إنشاء سند الصرف وترحيله بنجاح — ' . $voucher->voucher_number);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'خطأ أثناء الحفظ: ' . $e->getMessage());
        }
    }

    public function show(PaymentVoucher $payment)
    {
        $payment->load('account', 'cashAccount', 'supplier', 'user', 'journalEntry.lines.account');
        $currency  = Setting::get('currency_symbol', 'ج.م');
        $storeName = Setting::get('store_name', 'الميّزان');
        return view('vouchers.payments.show', compact('payment', 'currency', 'storeName'));
    }

    public function pdf(PaymentVoucher $payment)
    {
        $payment->load('account', 'cashAccount', 'supplier', 'user', 'journalEntry');
        $currency     = Setting::get('currency_symbol', 'ج.م');
        $storeName    = Setting::get('store_name', 'الميّزان');
        $storeAddress = Setting::get('store_address', '');
        $storePhone   = Setting::get('store_phone', '');

        return PdfService::arabic('pdf.payment_voucher',
            compact('payment', 'currency', 'storeName', 'storeAddress', 'storePhone'))
            ->download('payment-' . $payment->voucher_number . '.pdf');
    }

    public function destroy(PaymentVoucher $payment)
    {
        if ($payment->is_posted) {
            return back()->with('error', 'لا يمكن حذف سند صرف مُرحَّل. استخدم قيد تصحيح عكسي.');
        }
        $payment->delete();
        return redirect()->route('vouchers.payments.index')
            ->with('success', 'تم حذف السند بنجاح');
    }

    // ── private helpers ──────────────────────────────────────────────────────

    private function actionButtons(PaymentVoucher $v): string
    {
        $show = '<a href="' . route('vouchers.payments.show', $v) . '" class="btn btn-sm btn-info btn-action" title="عرض"><i class="bi bi-eye"></i></a>';
        $pdf  = '<a href="' . route('vouchers.payments.pdf',  $v) . '" class="btn btn-sm btn-outline-danger btn-action" title="PDF" target="_blank"><i class="bi bi-file-earmark-pdf"></i></a>';
        $del  = '';

        /** @var \App\Models\User $user */
        $user = auth()->user();
        if ($user->can('vouchers.delete') && !$v->is_posted) {
            $token = csrf_token();
            $del = '<form action="' . route('vouchers.payments.destroy', $v) . '" method="POST" class="d-inline"'
                . ' onsubmit="return confirm(\'هل أنت متأكد من حذف هذا السند؟\')">'
                . '<input type="hidden" name="_token" value="' . $token . '">'
                . '<input type="hidden" name="_method" value="DELETE">'
                . '<button class="btn btn-sm btn-outline-secondary btn-action" title="حذف"><i class="bi bi-trash"></i></button></form>';
        }

        return '<div class="d-flex gap-1 flex-nowrap">' . $show . $pdf . $del . '</div>';
    }
}
