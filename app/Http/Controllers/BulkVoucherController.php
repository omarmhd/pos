<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
use App\Models\Setting;
use App\Services\BranchAccountingService;
use App\Services\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * السندات المتعددة (مستوحاة من "إيصالات القبض وسندات الصرف المتعددة" في الأصيل):
 * إدخال دفعي لعشرات سندات القبض أو الصرف في شاشة واحدة —
 * كل سطر يُنشئ سنداً مستقلاً مُرحَّلاً بقيده.
 */
class BulkVoucherController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:vouchers.create');
    }

    public function create()
    {
        $accounts = Account::where('is_active', true)->where('is_header', false)->orderBy('code')->get();
        $currency = Setting::get('currency_symbol', 'ج.م');
        $branches = \App\Models\Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);

        $cashCode     = Setting::get('account_cash_code', '1000');
        $userBranchId = auth()->user()?->branch_id;
        $defaultCashAccount = $userBranchId
            ? Account::find(BranchAccountingService::cashAccountId($userBranchId))
            : Account::where('code', $cashCode)->where('is_active', true)->first();

        $branchLocked = $this->isBranchLocked();

        return view('vouchers.bulk.create', compact(
            'accounts', 'currency', 'branches', 'defaultCashAccount', 'branchLocked'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'voucher_type'      => 'required|in:receipt,payment',
            'voucher_date'      => 'required|date',
            'payment_method'    => 'required|in:cash,bank,mobile_wallet',
            'cash_account_id'   => 'required|exists:accounts,id',
            'rows'              => 'required|array|min:1',
            'rows.*.party'      => 'nullable|string|max:255',
            'rows.*.account_id' => 'nullable|exists:accounts,id',
            'rows.*.amount'     => 'nullable|numeric|min:0.01',
            'rows.*.reference'  => 'nullable|string|max:255',
            'rows.*.notes'      => 'nullable|string|max:500',
        ]);

        $cashAccount = Account::findOrFail($request->cash_account_id);
        if ($cashAccount->is_header) {
            return back()->withErrors(['cash_account_id' => 'لا يمكن استخدام حساب رئيسي (تجميعي)'])->withInput();
        }

        // الأسطر الفعلية فقط (حساب + مبلغ + اسم)
        $rows = collect($request->rows)
            ->filter(fn($r) => !empty($r['account_id']) && !empty($r['amount']) && !empty($r['party']))
            ->values();

        if ($rows->isEmpty()) {
            return back()->withInput()->with('error', 'لا توجد أسطر مكتملة — كل سطر يحتاج: الاسم، الحساب، والمبلغ');
        }

        foreach ($rows as $i => $r) {
            if ((int) $r['account_id'] === $cashAccount->id) {
                return back()->withInput()->with('error', 'السطر ' . ($i + 1) . ': الحساب المقابل لا يمكن أن يكون نفس حساب النقدية');
            }
        }

        $isReceipt = $request->voucher_type === 'receipt';

        $resolvedBranch = $this->isBranchLocked()
            ? (auth()->user()?->branch_id ?? Setting::get('default_branch_id'))
            : ($request->input('branch_id') ?: auth()->user()?->branch_id ?: Setting::get('default_branch_id'));

        DB::beginTransaction();
        try {
            $service = new LedgerPostingService();
            $created = [];

            foreach ($rows as $r) {
                $payload = [
                    'voucher_date'    => $request->voucher_date,
                    'account_id'      => (int) $r['account_id'],
                    'cash_account_id' => $cashAccount->id,
                    'amount'          => round((float) $r['amount'], 2),
                    'payment_method'  => $request->payment_method,
                    'reference'       => $r['reference'] ?? null,
                    'notes'           => $r['notes'] ?? null,
                    'user_id'         => auth()->id(),
                    'branch_id'       => $resolvedBranch,
                ];

                if ($isReceipt) {
                    $voucher = ReceiptVoucher::create($payload + ['received_from' => $r['party']]);
                    $service->postReceiptVoucher($voucher);
                } else {
                    $voucher = PaymentVoucher::create($payload + ['paid_to' => $r['party']]);
                    $service->postPaymentVoucher($voucher);
                }

                $created[] = $voucher->fresh()->voucher_number;
            }

            DB::commit();

            $route = $isReceipt ? 'vouchers.receipts.index' : 'vouchers.payments.index';

            return redirect()->route($route)->with('success',
                'تم إنشاء وترحيل ' . count($created) . ' سند بنجاح ('
                . implode('، ', array_slice($created, 0, 5))
                . (count($created) > 5 ? ' …' : '') . ')');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'فشلت العملية بالكامل (لم يُحفظ أي سند): ' . $e->getMessage());
        }
    }
}
