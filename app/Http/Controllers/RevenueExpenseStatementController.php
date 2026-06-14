<?php

namespace App\Http\Controllers;

use App\Models\CustomsDeclaration;
use App\Models\ExpenseInvoice;
use App\Models\FixedAsset;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\RevenueExpenseStatement;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\ServiceInvoice;
use App\Models\Setting;
use App\Services\PdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * كشوف الإيرادات والمصروفات — إقرار دوري بعضوية حصرية للفواتير:
 *  - كل فاتورة تدخل في كشف واحد فقط (res_statement_id).
 *  - "الحجز": إلغاء تحديد فاتورة في المعاينة يبقيها بلا كشف لتلتقط في الكشف التالي.
 *  - تحذير المتأخرات: فواتير أُدخلت بعد حفظ كشف وتاريخها يقع ضمن مداه.
 */
class RevenueExpenseStatementController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:res.view')->only(['index', 'show', 'print']);
        $this->middleware('can:res.manage')->only(['create', 'store', 'edit', 'update', 'destroy']);
    }

    public function index()
    {
        $statements = RevenueExpenseStatement::with('user')
            ->withCount('sales', 'purchases', 'expenseInvoices')
            ->orderByDesc('statement_date')->orderByDesc('id')
            ->get();
        $currency = Setting::get('currency_symbol', 'ج.م');

        // ── تحذير الفواتير المتأخرة: غير معينة وتاريخها ≤ آخر تاريخ قطع ──────
        $lateCount    = 0;
        $latestCutoff = $statements->max('statement_date');
        if ($latestCutoff) {
            $lateCount = $this->candidatesQuery('sales', $latestCutoff)->count()
                       + $this->candidatesQuery('purchases', $latestCutoff)->count()
                       + $this->candidatesQuery('sale_returns', $latestCutoff)->count()
                       + $this->candidatesQuery('purchase_returns', $latestCutoff)->count()
                       + $this->candidatesQuery('expense_invoices', $latestCutoff)->count()
                       + $this->candidatesQuery('fixed_assets', $latestCutoff)->count()
                       + $this->candidatesQuery('customs_declarations', $latestCutoff)->count();
        }

        return view('res.index', compact('statements', 'currency', 'lateCount', 'latestCutoff'));
    }

    /**
     * إنشاء كشف: اختيار تاريخ القطع ← معاينة المرشحين مع إمكانية الحجز.
     */
    public function create(Request $request)
    {
        $currency      = Setting::get('currency_symbol', 'ج.م');
        $statementDate = $request->input('statement_date');
        $candidates    = $statementDate ? $this->loadCandidates($statementDate) : null;

        return view('res.create', compact('currency', 'statementDate', 'candidates'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'statement_date' => 'required|date',
            'description'    => 'nullable|string|max:255',
            'include'        => 'nullable|array',
        ]);

        DB::beginTransaction();
        try {
            $statement = RevenueExpenseStatement::create([
                'statement_date' => $request->statement_date,
                'description'    => $request->description,
                'user_id'        => auth()->id(),
            ]);

            $this->assignMembers($statement, $request->input('include', []));
            $this->recomputeTotals($statement);

            DB::commit();

            return redirect()->route('res.show', $statement)
                ->with('success', 'تم إنشاء الكشف — ' . $statement->fresh()->number);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'خطأ: ' . $e->getMessage());
        }
    }

    public function show(RevenueExpenseStatement $re_statement)
    {
        $re_statement->load('user', 'sales.customer', 'saleReturns', 'purchases.supplier',
                            'purchaseReturns', 'expenseInvoices', 'fixedAssets', 'customsDeclarations.supplier',
                            'serviceInvoices.customer');
        $currency = Setting::get('currency_symbol', 'ج.م');

        // فواتير متأخرة تخص مدى هذا الكشف ولم تدخل أي كشف
        $late = $this->loadCandidates($re_statement->statement_date->toDateString());
        $lateCount = collect($late)->sum(fn($c) => $c['docs']->count());

        return view('res.show', compact('re_statement', 'currency', 'lateCount'));
    }

    /** طباعة الكشف PDF (تخطيط صناديق الإيرادات/المصروفات/الأصول/الجمارك/الملخص) */
    public function print(RevenueExpenseStatement $re_statement)
    {
        $re_statement->load('user', 'sales.customer', 'saleReturns', 'purchases.supplier',
                            'purchaseReturns', 'expenseInvoices', 'fixedAssets', 'customsDeclarations.supplier',
                            'serviceInvoices.customer');
        $currency     = Setting::get('currency_symbol', 'ج.م');
        $storeName    = Setting::get('store_name', 'الميّزان');
        $storeAddress = Setting::get('store_address', '');
        $storePhone   = Setting::get('store_phone', '');

        return PdfService::arabic('pdf.res',
            compact('re_statement', 'currency', 'storeName', 'storeAddress', 'storePhone'))
            ->stream($re_statement->number . '.pdf');
    }

    /**
     * تعديل: الأعضاء الحاليون (محددون) + المرشحون غير المعينين ≤ تاريخ القطع (متأخرات).
     */
    public function edit(RevenueExpenseStatement $re_statement)
    {
        $currency   = Setting::get('currency_symbol', 'ج.م');
        $cutoff     = $re_statement->statement_date->toDateString();
        $candidates = $this->loadCandidates($cutoff, $re_statement->id);

        return view('res.edit', compact('re_statement', 'currency', 'candidates'));
    }

    public function update(Request $request, RevenueExpenseStatement $re_statement)
    {
        $request->validate([
            'description' => 'nullable|string|max:255',
            'include'     => 'nullable|array',
        ]);

        DB::beginTransaction();
        try {
            // تحرير جميع الأعضاء ثم إعادة التعيين حسب الاختيار (الحجز = عدم التحديد)
            $this->releaseMembers($re_statement);

            $re_statement->update(['description' => $request->description]);
            $this->assignMembers($re_statement, $request->input('include', []));
            $this->recomputeTotals($re_statement);

            DB::commit();

            return redirect()->route('res.show', $re_statement)
                ->with('success', 'تم تحديث الكشف وإعادة احتساب الإجماليات');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'خطأ: ' . $e->getMessage());
        }
    }

    public function destroy(RevenueExpenseStatement $re_statement)
    {
        DB::beginTransaction();
        try {
            // تحرير الفواتير ليلتقطها كشف لاحق
            $this->releaseMembers($re_statement);
            $re_statement->delete();

            DB::commit();

            return redirect()->route('res.index')
                ->with('success', 'تم حذف الكشف وتحرير فواتيره (ستظهر في الكشف التالي)');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'خطأ: ' . $e->getMessage());
        }
    }

    // ── private helpers ──────────────────────────────────────────────────────

    /** استعلام المرشحين: غير معينين لأي كشف وتاريخهم ≤ تاريخ القطع */
    private function candidatesQuery(string $type, $cutoff, ?int $includeStatementId = null)
    {
        $map = [
            'sales'                => [Sale::class,               'created_at'],
            'sale_returns'         => [SaleReturn::class,         'return_date'],
            'purchases'            => [Purchase::class,           'created_at'],
            'purchase_returns'     => [PurchaseReturn::class,     'return_date'],
            'expense_invoices'     => [ExpenseInvoice::class,     'invoice_date'],
            'fixed_assets'         => [FixedAsset::class,         'purchase_date'],
            'customs_declarations' => [CustomsDeclaration::class, 'declaration_date'],
            'service_invoices'     => [ServiceInvoice::class,     'invoice_date'],
        ];
        [$model, $dateCol] = $map[$type];

        return $model::query()
            ->whereDate($dateCol, '<=', $cutoff)
            ->where(function ($q) use ($includeStatementId) {
                $q->whereNull('res_statement_id');
                if ($includeStatementId) {
                    $q->orWhere('res_statement_id', $includeStatementId);
                }
            });
    }

    /** تحميل المرشحين للمعاينة، مقسمين حسب النوع */
    private function loadCandidates(string $cutoff, ?int $includeStatementId = null): array
    {
        return [
            'sales' => [
                'label' => 'المبيعات', 'docs' => $this->candidatesQuery('sales', $cutoff, $includeStatementId)
                    ->with('customer:id,name')->orderBy('created_at')->get(),
            ],
            'sale_returns' => [
                'label' => 'مرجع المبيعات', 'docs' => $this->candidatesQuery('sale_returns', $cutoff, $includeStatementId)
                    ->orderBy('return_date')->get(),
            ],
            'purchases' => [
                'label' => 'المشتريات', 'docs' => $this->candidatesQuery('purchases', $cutoff, $includeStatementId)
                    ->with('supplier:id,name')->orderBy('created_at')->get(),
            ],
            'purchase_returns' => [
                'label' => 'مرجع المشتريات', 'docs' => $this->candidatesQuery('purchase_returns', $cutoff, $includeStatementId)
                    ->orderBy('return_date')->get(),
            ],
            'expense_invoices' => [
                'label' => 'المصاريف', 'docs' => $this->candidatesQuery('expense_invoices', $cutoff, $includeStatementId)
                    ->orderBy('invoice_date')->get(),
            ],
            'fixed_assets' => [
                'label' => 'الأصول الرأسمالية', 'docs' => $this->candidatesQuery('fixed_assets', $cutoff, $includeStatementId)
                    ->orderBy('purchase_date')->get(),
            ],
            'customs_declarations' => [
                'label' => 'الإقرارات الجمركية', 'docs' => $this->candidatesQuery('customs_declarations', $cutoff, $includeStatementId)
                    ->with('supplier:id,name')->orderBy('declaration_date')->get(),
            ],
            'service_invoices' => [
                'label' => 'فواتير إيراد الخدمات', 'docs' => $this->candidatesQuery('service_invoices', $cutoff, $includeStatementId)
                    ->with('customer:id,name')->orderBy('invoice_date')->get(),
            ],
        ];
    }

    /** تعيين الأعضاء المختارين للكشف (الأمان: غير المعينين فقط وضمن تاريخ القطع) */
    private function assignMembers(RevenueExpenseStatement $statement, array $include): void
    {
        $cutoff = $statement->statement_date instanceof \Carbon\Carbon
            ? $statement->statement_date->toDateString()
            : $statement->statement_date;

        foreach (['sales', 'sale_returns', 'purchases', 'purchase_returns', 'expense_invoices',
                  'fixed_assets', 'customs_declarations', 'service_invoices'] as $type) {
            $ids = array_map('intval', $include[$type] ?? []);
            if (!$ids) {
                continue;
            }
            $this->candidatesQuery($type, $cutoff, $statement->id)
                ->whereIn('id', $ids)
                ->update(['res_statement_id' => $statement->id]);
        }
    }

    /** تحرير جميع أعضاء الكشف */
    private function releaseMembers(RevenueExpenseStatement $statement): void
    {
        foreach ([Sale::class, SaleReturn::class, Purchase::class, PurchaseReturn::class, ExpenseInvoice::class,
                  FixedAsset::class, CustomsDeclaration::class, ServiceInvoice::class] as $model) {
            $model::where('res_statement_id', $statement->id)->update(['res_statement_id' => null]);
        }
    }

    /**
     * احتساب لقطة الإجماليات من الأعضاء (بمسميات IFRS):
     *  - المبالغ صافية من الضريبة، والضرائب في أعمدتها.
     *  - صافي ض.ق.م المستحقة (Net VAT Payable) = ضريبة المخرجات − ضريبة المدخلات
     *    (مشتريات + مصاريف + الأصول الرأسمالية + الواردات الجمركية).
     *  - هامش مجمل الربح (Gross Profit Margin) = صافي الربح ÷ صافي الإيرادات × 100.
     *
     * مذكرات لتفادي الازدواج المحاسبي (لا تُضاف للإجماليات، عرض فقط):
     *  - إيرادات الخدمات = جزء من المبيعات (أصناف من نوع "خدمة").
     *  - الإشعارات الدائنة = جزء من مرتجعات المبيعات (refund_method = credit_note).
     *  - الإشعارات المدينة = جزء من مرتجعات المشتريات (refund_method = debit_note).
     *
     * الأصول الرأسمالية والإقرارات الجمركية لا تدخل في "هامش الربح" (ليست مصروفاً تشغيلياً)،
     * وإنما تؤثر فقط في صافي ض.ق.م المستحقة عبر ضريبة مدخلاتها.
     */
    private function recomputeTotals(RevenueExpenseStatement $statement): void
    {
        $id = $statement->id;

        $salesTax    = (float) Sale::where('res_statement_id', $id)->sum('tax');
        $salesGross  = (float) Sale::where('res_statement_id', $id)->sum('total_amount');
        $salesNet    = round($salesGross - $salesTax, 2);

        // فواتير إيراد الخدمات المستقلة (IFRS 15): تُضاف للإيراد وضريبة المخرجات
        $svcInvGross = (float) ServiceInvoice::where('res_statement_id', $id)->sum('total_amount');
        $svcInvTax   = (float) ServiceInvoice::where('res_statement_id', $id)->sum('tax_amount');
        $svcInvNet   = round($svcInvGross - $svcInvTax, 2);
        $salesTax    = round($salesTax + $svcInvTax, 2);
        $salesNet    = round($salesNet + $svcInvNet, 2);

        $saleRet     = (float) SaleReturn::where('res_statement_id', $id)->sum('total_amount');

        $purchGross  = (float) Purchase::where('res_statement_id', $id)->sum('total_amount');
        $purchTax    = (float) Purchase::where('res_statement_id', $id)->sum('tax_amount');
        $purchNet    = round($purchGross - $purchTax, 2);

        $purchRet    = (float) PurchaseReturn::where('res_statement_id', $id)->sum('total_amount');

        $expGross    = (float) ExpenseInvoice::where('res_statement_id', $id)->sum('total_amount');
        $expTax      = (float) ExpenseInvoice::where('res_statement_id', $id)->sum('tax_amount');
        $expNet      = round($expGross - $expTax, 2);

        // ── الأصول الرأسمالية: التكلفة + ضريبة المدخلات ───────────────────────
        $assetsNet   = (float) FixedAsset::where('res_statement_id', $id)->sum('purchase_cost');
        $assetsTax   = (float) FixedAsset::where('res_statement_id', $id)->sum('tax_amount');

        // ── الإقرارات الجمركية: قيمة الواردات/الرسوم + ض.ق.م الواردات (مدخلات) ──
        $customsNet  = (float) CustomsDeclaration::where('res_statement_id', $id)->sum('total_amount');
        $customsTax  = (float) CustomsDeclaration::where('res_statement_id', $id)->sum('tax_amount');

        // ── مذكرة: إيرادات الخدمات (جزء من المبيعات — أصناف نوعها "خدمة") ──────
        $svc = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.res_statement_id', $id)
            ->where('products.product_type', Product::TYPE_SERVICE)
            ->selectRaw('COALESCE(SUM(sale_items.total_price),0) AS net, COALESCE(SUM(sale_items.vat_amount),0) AS tax')
            ->first();
        $servicesNet = round((float) ($svc->net ?? 0) + $svcInvNet, 2);
        $servicesTax = round((float) ($svc->tax ?? 0) + $svcInvTax, 2);

        // ── مذكرة: الإشعارات الدائنة / المدينة (أجزاء من المرتجعات) ────────────
        $creditNotes = (float) SaleReturn::where('res_statement_id', $id)
            ->where('refund_method', 'credit_note')->sum('total_amount');
        $debitNotes  = (float) PurchaseReturn::where('res_statement_id', $id)
            ->where('refund_method', 'debit_note')->sum('total_amount');

        // ── الإجماليات ───────────────────────────────────────────────────────
        $revenueNet  = round($salesNet - $saleRet, 2);
        $costNet     = round($purchNet - $purchRet + $expNet, 2);
        $netAmount   = round($revenueNet - $costNet, 2);
        $netVat      = round($salesTax - $purchTax - $expTax - $assetsTax - $customsTax, 2);
        $profitPct   = $revenueNet > 0 ? round($netAmount / $revenueNet * 100, 2) : 0;

        $statement->update([
            'sales_amount'            => $salesNet,
            'sales_tax'               => $salesTax,
            'sales_returns_amount'    => $saleRet,
            'services_amount'         => $servicesNet,
            'services_tax'            => $servicesTax,
            'credit_notes_amount'     => $creditNotes,
            'purchases_amount'        => $purchNet,
            'purchases_tax'           => $purchTax,
            'purchase_returns_amount' => $purchRet,
            'debit_notes_amount'      => $debitNotes,
            'assets_amount'           => round($assetsNet, 2),
            'assets_tax'              => round($assetsTax, 2),
            'customs_amount'          => round($customsNet, 2),
            'customs_tax'             => round($customsTax, 2),
            'expenses_amount'         => $expNet,
            'expenses_tax'            => $expTax,
            'net_amount'              => $netAmount,
            'net_vat'                 => $netVat,
            'profit_percent'          => $profitPct,
        ]);
    }
}
