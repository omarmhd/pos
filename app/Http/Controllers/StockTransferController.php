<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StockLevel;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Warehouse;
use App\Services\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class StockTransferController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:stock_transfers.view')->only(['index', 'show']);
        $this->middleware('can:stock_transfers.create')->only(['create', 'store']);
        $this->middleware('can:stock_transfers.complete')->only(['complete']);
        $this->middleware('can:stock_transfers.cancel')->only(['cancel']);
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $branchId = $this->effectiveBranchId($request);
            $query = StockTransfer::with('fromWarehouse', 'toWarehouse', 'user')
                ->select('stock_transfers.*')
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId));

            return DataTables::eloquent($query)
                ->addColumn('from_wh',      fn($t) => e($t->fromWarehouse?->name ?? '—'))
                ->addColumn('to_wh',        fn($t) => e($t->toWarehouse?->name ?? '—'))
                ->addColumn('user_name',    fn($t) => e($t->user?->name ?? '—'))
                ->addColumn('date',         fn($t) => $t->transfer_date->format('Y-m-d'))
                ->addColumn('status_badge', fn($t) => "<span class='badge bg-{$t->statusColor()}'>{$t->statusLabel()}</span>")
                ->addColumn('action',       fn($t) => $this->actionButtons($t))
                ->rawColumns(['status_badge', 'action'])
                ->make(true);
        }

        $branches     = Branch::where('is_active', true)->orderBy('name')->get(['id','name','code']);
        $branchId     = $this->effectiveBranchId($request);
        $branchLocked = $this->isBranchLocked();
        $currency     = Setting::get('currency_symbol', 'ج.م');

        return view('stock-transfers.index', compact('branches', 'branchId', 'branchLocked', 'currency'));
    }

    public function create()
    {
        $warehouses = Warehouse::where('is_active', true)
            ->with('branch:id,name')
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();
        $currency = Setting::get('currency_symbol', 'ج.م');
        $branches = Branch::where('is_active', true)->orderBy('name')->get(['id','name','code']);

        return view('stock-transfers.create', compact('warehouses', 'currency', 'branches'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id'   => 'required|exists:warehouses,id|different:from_warehouse_id',
            'transfer_date'     => 'required|date',
            'notes'             => 'nullable|string',
            'items'             => 'required|array|min:1',
            'items.*.product_id'=> 'required|exists:products,id',
            'items.*.quantity'  => 'required|numeric|min:0.001',
        ]);

        DB::beginTransaction();
        try {
            $transfer = StockTransfer::create([
                'from_warehouse_id' => $request->from_warehouse_id,
                'to_warehouse_id'   => $request->to_warehouse_id,
                'branch_id'         => auth()->user()->branch_id ?? Setting::get('default_branch_id'),
                'user_id'           => auth()->id(),
                'transfer_date'     => $request->transfer_date,
                'status'            => 'draft',
                'notes'             => $request->notes,
            ]);

            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id'        => $item['product_id'],
                    'quantity'          => $item['quantity'],
                    'unit_cost'         => $product?->cost_price ?? 0,
                    'notes'             => $item['notes'] ?? null,
                ]);
            }

            DB::commit();

            return redirect()->route('stock-transfers.show', $transfer)
                ->with('success', 'تم إنشاء طلب التحويل — ' . $transfer->transfer_number);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'خطأ: ' . $e->getMessage());
        }
    }

    public function show(StockTransfer $stockTransfer)
    {
        $stockTransfer->load('fromWarehouse.branch', 'toWarehouse.branch',
            'user', 'branch', 'items.product');

        // Get current stock levels for both warehouses
        $productIds = $stockTransfer->items->pluck('product_id')->all();
        $fromLevels = StockLevel::where('warehouse_id', $stockTransfer->from_warehouse_id)
            ->whereIn('product_id', $productIds)->pluck('quantity', 'product_id');
        $toLevels   = StockLevel::where('warehouse_id', $stockTransfer->to_warehouse_id)
            ->whereIn('product_id', $productIds)->pluck('quantity', 'product_id');

        $currency = Setting::get('currency_symbol', 'ج.م');
        return view('stock-transfers.show', compact(
            'stockTransfer', 'fromLevels', 'toLevels', 'currency'
        ));
    }

    /**
     * Execute the transfer: move stock from source to destination warehouse.
     * Creates StockMovement audit records. NO GL journal entry.
     */
    public function complete(Request $request, StockTransfer $stockTransfer)
    {
        if ($stockTransfer->status !== 'draft') {
            return back()->with('error', 'يمكن تنفيذ التحويلات بحالة "مسودة" فقط');
        }

        DB::beginTransaction();
        try {
            $stockTransfer->load('items.product');

            foreach ($stockTransfer->items as $item) {
                // Validate available stock in source warehouse
                $available = WarehouseService::levelFor(
                    $stockTransfer->from_warehouse_id,
                    $item->product_id
                );

                if ($available < (float) $item->quantity) {
                    throw new \RuntimeException(
                        "الكمية المتاحة في المخزن المصدر للصنف \"{$item->product?->name}\" هي "
                        . number_format($available, 2)
                        . " — غير كافية للتحويل"
                    );
                }

                // Move stock: out from source, in to destination
                WarehouseService::out(
                    $stockTransfer->from_warehouse_id,
                    $item->product_id,
                    (float) $item->quantity
                );
                WarehouseService::in(
                    $stockTransfer->to_warehouse_id,
                    $item->product_id,
                    (float) $item->quantity
                );

                // Audit trail in StockMovements
                \App\Models\StockMovement::create([
                    'product_id'     => $item->product_id,
                    'warehouse_id'   => $stockTransfer->from_warehouse_id,
                    'reference_type' => StockTransfer::class,
                    'reference_id'   => $stockTransfer->id,
                    'quantity'       => $item->quantity,
                    'cost'           => $item->unit_cost,
                    'movement_type'  => 'out',
                    'notes'          => 'تحويل صادر إلى ' . $stockTransfer->toWarehouse?->name,
                ]);

                \App\Models\StockMovement::create([
                    'product_id'     => $item->product_id,
                    'warehouse_id'   => $stockTransfer->to_warehouse_id,
                    'reference_type' => StockTransfer::class,
                    'reference_id'   => $stockTransfer->id,
                    'quantity'       => $item->quantity,
                    'cost'           => $item->unit_cost,
                    'movement_type'  => 'in',
                    'notes'          => 'تحويل وارد من ' . $stockTransfer->fromWarehouse?->name,
                ]);
            }

            $stockTransfer->update([
                'status'       => 'completed',
                'completed_at' => now(),
            ]);

            DB::commit();

            return redirect()->route('stock-transfers.show', $stockTransfer)
                ->with('success', 'تم تنفيذ التحويل بنجاح — ' . $stockTransfer->transfer_number);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'خطأ: ' . $e->getMessage());
        }
    }

    public function cancel(StockTransfer $stockTransfer)
    {
        if ($stockTransfer->status !== 'draft') {
            return back()->with('error', 'يمكن إلغاء التحويلات بحالة "مسودة" فقط');
        }
        $stockTransfer->update(['status' => 'cancelled']);
        return back()->with('success', 'تم إلغاء طلب التحويل');
    }

    /** AJAX: get stock level for a product in a warehouse */
    public function stockLevel(Request $request)
    {
        $level = StockLevel::where('warehouse_id', $request->warehouse_id)
            ->where('product_id', $request->product_id)
            ->value('quantity') ?? 0;
        return response()->json(['quantity' => (float) $level]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function actionButtons(StockTransfer $t): string
    {
        /** @var \App\Models\User|null $u */
        $u    = auth()->user();
        $show = '<a href="' . route('stock-transfers.show', $t) . '" class="btn btn-sm btn-info btn-action" title="عرض"><i class="bi bi-eye"></i></a>';
        $exec = $cancel = '';

        if ($t->status === 'draft') {
            if ($u?->can('stock_transfers.complete')) {
                $exec = '<form action="' . route('stock-transfers.complete', $t) . '" method="POST" class="d-inline"
                    onsubmit="return confirm(\'تنفيذ التحويل؟ سيُنقل المخزون فوراً.\')">
                    ' . csrf_field() . '
                    <button class="btn btn-sm btn-success btn-action" title="تنفيذ"><i class="bi bi-check2-circle"></i></button>
                </form>';
            }
            if ($u?->can('stock_transfers.cancel')) {
                $cancel = '<form action="' . route('stock-transfers.cancel', $t) . '" method="POST" class="d-inline"
                    onsubmit="return confirm(\'إلغاء التحويل؟\')">
                    ' . csrf_field() . '
                    <button class="btn btn-sm btn-outline-danger btn-action" title="إلغاء"><i class="bi bi-x-circle"></i></button>
                </form>';
            }
        }

        return '<div class="d-flex gap-1 flex-nowrap">' . $show . $exec . $cancel . '</div>';
    }
}
