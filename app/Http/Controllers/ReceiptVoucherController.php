<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Customer;
use App\Models\CustomerDeposit;
use App\Models\ReceiptVoucher;
use App\Models\Setting;
use App\Services\BranchAccountingService;
use App\Services\LedgerPostingService;
use App\Services\PdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class ReceiptVoucherController extends Controller
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
        return view('vouchers.receipts.index', compact('branches', 'branchId', 'branchLocked'));
    }

    public function data(Request $request)
    {
        $branchId = $this->effectiveBranchId($request);
        $from     = $request->filled('from') ? $request->from : null;
        $to       = $request->filled('to')   ? $request->to   : null;
        $method   = $request->filled('method') ? $request->method : null;

        try {
            // ── سندات القبض العامة ──────────────────────────────────────────
            $vouchers = ReceiptVoucher::with('user', 'account', 'cashAccount')
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->when($from,     fn($q) => $q->whereDate('voucher_date', '>=', $from))
                ->when($to,       fn($q) => $q->whereDate('voucher_date', '<=', $to))
                ->when($method,   fn($q) => $q->where('payment_method', $method))
                ->orderByDesc('voucher_date')
                ->get()
                ->map(fn($v) => [
                    'voucher_number'   => $v->voucher_number,
                    'date_fmt'         => $v->voucher_date->format('Y-m-d'),
                    'type_badge'       => '<span class="badge bg-secondary">قبض عام</span>',
                    'party'            => e($v->received_from),
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

            // ── إيداعات العملاء ─────────────────────────────────────────────
            $depositAcctLabel = Account::where('code', Setting::get('account_customer_deposits_code', '2050'))->value('name') ?? 'سُلَف العملاء';
            $cashAcctLabel    = Account::where('code', Setting::get('account_cash_code', '1000'))->value('name')            ?? 'الصندوق';
            $depositCode      = Setting::get('account_customer_deposits_code', '2050');

            $deposits = CustomerDeposit::with('customer', 'user')
                ->where('type', 'deposit')
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->when($from,     fn($q) => $q->whereDate('voucher_date', '>=', $from))
                ->when($to,       fn($q) => $q->whereDate('voucher_date', '<=', $to))
                ->when($method,   fn($q) => $q->where('payment_method', $method))
                ->orderByDesc('voucher_date')
                ->get()
                ->map(fn($d) => [
                    'voucher_number'   => $d->voucher_number,
                    'date_fmt'         => $d->voucher_date->format('Y-m-d'),
                    'type_badge'       => '<span class="badge bg-primary">إيداع عميل</span>',
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

            $rows = $vouchers->merge($deposits)
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
        $customers = Customer::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $currency  = Setting::get('currency_symbol', 'ج.م');
        $branches  = \App\Models\Branch::where('is_active', true)->orderBy('name')->get(['id','name','code']);

        $cashCode = Setting::get('account_cash_code', '1000');
        $bankCode = Setting::get('account_bank_code', '1100');

        // Pre-select the branch-specific cash account (1000.XX) if the user belongs to a branch.
        // Fallback to system default cash account for admins/global users.
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

        return view('vouchers.receipts.create', compact(
            'accounts', 'customers', 'currency', 'defaultCashAccount',
            'cashCode', 'bankCode', 'branches', 'branchId', 'branchLocked'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'voucher_date'    => 'required|date',
            'received_from'   => 'required|string|max:255',
            'customer_id'     => 'nullable|exists:customers,id',
            'account_id'      => 'required|exists:accounts,id',
            'cash_account_id' => 'required|exists:accounts,id',
            'amount'          => 'required|numeric|min:0.01',
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

        DB::beginTransaction();
        try {
            $voucher = ReceiptVoucher::create([
                'voucher_date'    => $request->voucher_date,
                'received_from'   => $request->received_from,
                'customer_id'     => $request->customer_id ?: null,
                'account_id'      => $request->account_id,
                'cash_account_id' => $request->cash_account_id,
                'amount'          => $request->amount,
                'payment_method'  => $request->payment_method,
                'reference'       => $request->reference,
                'notes'           => $request->notes,
                'user_id'         => auth()->id(),
                'branch_id'       => auth()->user()->branch_id
                                     ?? \App\Models\Setting::get('default_branch_id'),
            ]);

            (new LedgerPostingService())->postReceiptVoucher($voucher);

            DB::commit();

            return redirect()->route('vouchers.receipts.show', $voucher)
                ->with('success', 'تم إنشاء سند القبض وترحيله بنجاح — ' . $voucher->voucher_number);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'خطأ أثناء الحفظ: ' . $e->getMessage());
        }
    }

    public function show(ReceiptVoucher $receipt)
    {
        $receipt->load('account', 'cashAccount', 'customer', 'user', 'journalEntry.lines.account');
        $currency  = Setting::get('currency_symbol', 'ج.م');
        $storeName = Setting::get('store_name', 'الميّزان');
        return view('vouchers.receipts.show', compact('receipt', 'currency', 'storeName'));
    }

    public function pdf(ReceiptVoucher $receipt)
    {
        $receipt->load('account', 'cashAccount', 'customer', 'user', 'journalEntry');
        $currency     = Setting::get('currency_symbol', 'ج.م');
        $storeName    = Setting::get('store_name', 'الميّزان');
        $storeAddress = Setting::get('store_address', '');
        $storePhone   = Setting::get('store_phone', '');

        return PdfService::arabic('pdf.receipt_voucher',
            compact('receipt', 'currency', 'storeName', 'storeAddress', 'storePhone'))
            ->download('receipt-' . $receipt->voucher_number . '.pdf');
    }

    public function destroy(ReceiptVoucher $receipt)
    {
        if ($receipt->is_posted) {
            return back()->with('error', 'لا يمكن حذف سند قبض مُرحَّل. استخدم قيد تصحيح عكسي.');
        }
        $receipt->delete();
        return redirect()->route('vouchers.receipts.index')
            ->with('success', 'تم حذف السند بنجاح');
    }

    // ── private helpers ──────────────────────────────────────────────────────

    private function actionButtons(ReceiptVoucher $v): string
    {
        $show = '<a href="' . route('vouchers.receipts.show', $v) . '" class="btn btn-sm btn-info btn-action" title="عرض"><i class="bi bi-eye"></i></a>';
        $pdf  = '<a href="' . route('vouchers.receipts.pdf',  $v) . '" class="btn btn-sm btn-outline-danger btn-action" title="PDF" target="_blank"><i class="bi bi-file-earmark-pdf"></i></a>';
        $del  = '';

        /** @var \App\Models\User $user */
        $user = auth()->user();
        if ($user->can('vouchers.delete') && !$v->is_posted) {
            $token = csrf_token();
            $del = '<form action="' . route('vouchers.receipts.destroy', $v) . '" method="POST" class="d-inline"'
                . ' onsubmit="return confirm(\'هل أنت متأكد من حذف هذا السند؟\')">'
                . '<input type="hidden" name="_token" value="' . $token . '">'
                . '<input type="hidden" name="_method" value="DELETE">'
                . '<button class="btn btn-sm btn-outline-secondary btn-action" title="حذف"><i class="bi bi-trash"></i></button></form>';
        }

        return '<div class="d-flex gap-1 flex-nowrap">' . $show . $pdf . $del . '</div>';
    }
}
