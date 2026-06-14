<?php

namespace App\Http\Controllers;

use App\Models\JournalEntry;
use App\Models\Sale;
use App\Models\Setting;
use App\Services\PdfService;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class SaleController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:sales.view')->only(['index', 'show', 'pdf']);
        $this->middleware('can:sales.delete')->only(['destroy']);
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $branchId = $this->effectiveBranchId($request);
            $query = Sale::with('user')
                ->select('sales.*')
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId));

            return DataTables::eloquent($query)
                ->addColumn('user_name', fn($s) => e($s->user?->name ?? '—'))
                ->addColumn('payment_badge', fn($s) => $this->paymentBadge($s))
                ->addColumn('credit_badge', fn($s) => $s->is_credit
                    ? "<span class='badge bg-warning text-dark'>آجل</span>"
                    : "<span class='badge bg-light text-dark border'>نقدي</span>")
                ->addColumn('subtotal_fmt', fn($s) => number_format($s->subtotal, 2))
                ->addColumn('discount_fmt', fn($s) => $s->discount > 0 ? number_format($s->discount, 2) : '—')
                ->addColumn('total_fmt', fn($s) => number_format($s->total_amount, 2))
                ->addColumn('date', fn($s) => $s->created_at->format('Y-m-d H:i'))
                ->addColumn('action', fn($s) => $this->actionButtons($s))
                ->filterColumn('user_name', fn($q, $k) =>
                    $q->whereHas('user', fn($u) => $u->where('name', 'like', "%$k%")))
                ->rawColumns(['payment_badge', 'credit_badge', 'action'])
                ->make(true);
        }

        return view('sales.index');
    }

    public function show(Sale $sale)
    {
        $sale->load('user', 'customer', 'items.product');

        $journalEntry = JournalEntry::where('source_type', Sale::class)
            ->where('source_id', $sale->id)
            ->with('lines.account')
            ->first();

        $currency = Setting::get('currency_symbol', 'ج.م');

        return view('sales.show', compact('sale', 'journalEntry', 'currency'));
    }

    public function pdf(Sale $sale)
    {
        $sale->load('user', 'items.product', 'customer');
        return PdfService::arabic('pdf.invoice', compact('sale'))
            ->download('invoice-' . $sale->invoice_number . '.pdf');
    }

    public function destroy(Sale $sale)
    {
        if ($sale->is_posted) {
            return redirect()->route('sales.index')
                ->with('error', 'لا يمكن حذف الفاتورة بعد ترحيلها. استخدم قيد تصحيح.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($sale) {
            // Explicitly delete items to fire SaleItem::deleting() hook
            // which handles correct stock restoration via WarehouseService.
            foreach ($sale->items as $item) {
                $item->delete();
            }

            $sale->delete();
        });

        \App\Models\AuditLog::create([
            'user_id'        => auth()->id(),
            'auditable_type' => Sale::class,
            'auditable_id'   => $sale->id,
            'action'         => 'deleted',
            'old_values'     => null,
            'new_values'     => null,
            'ip_address'     => request()->ip(),
        ]);

        return redirect()->route('sales.index')->with('success', 'تم حذف عملية البيع بنجاح');
    }

    // ── private helpers ──────────────────────────────────────────────────────

    private function paymentBadge(Sale $sale): string
    {
        if ($sale->isMixed()) {
            return "<span class='badge bg-warning text-dark'>دفع مختلط</span>";
        }
        $method = $sale->payment_method;
        $map = [
            'cash'            => ['success', 'نقدي'],
            'card'            => ['primary', 'بطاقة'],
            'mobile_wallet'   => ['info',    'محفظة'],
            'deposit_balance' => ['purple',  'رصيد إيداع'],
        ];
        [$color, $label] = $map[$method] ?? ['secondary', $method];
        return "<span class='badge bg-{$color}'>{$label}</span>";
    }

    private function actionButtons(Sale $s): string
    {
        $show    = '<a href="'.route('sales.show', $s).'" class="btn btn-sm btn-info btn-action" title="عرض"><i class="bi bi-eye"></i></a>';
        $receipt = '<a href="'.route('pos.receipt', $s).'" class="btn btn-sm btn-secondary btn-action" target="_blank" title="إيصال"><i class="bi bi-receipt"></i></a>';
        $pdf     = '<a href="'.route('sales.pdf', $s).'" class="btn btn-sm btn-danger btn-action" target="_blank" title="PDF"><i class="bi bi-file-pdf"></i></a>';
        $del     = '';
        if (auth()->user()->can('sales.delete') && !$s->is_posted) {
            $token = csrf_token();
            $del = '<form action="'.route('sales.destroy', $s).'" method="POST" class="d-inline"'
                 . ' onsubmit="return confirm(\'هل أنت متأكد من حذف هذه الفاتورة؟\')">'
                 . '<input type="hidden" name="_token" value="'.$token.'">'
                 . '<input type="hidden" name="_method" value="DELETE">'
                 . '<button class="btn btn-sm btn-outline-danger btn-action" title="حذف"><i class="bi bi-trash"></i></button></form>';
        }
        return '<div class="d-flex gap-1 flex-nowrap">'.$show.$receipt.$pdf.$del.'</div>';
    }
}
