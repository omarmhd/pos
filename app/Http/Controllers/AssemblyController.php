<?php

namespace App\Http\Controllers;

use App\Models\Assembly;
use App\Models\AssemblyItem;
use App\Models\CostPriceHistory;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Services\LedgerPostingService;
use App\Services\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * التصنيع/التجميع (مستوحى من "حركة التصنيع" في الأصيل الذهبي):
 * يستهلك المكونات حسب معادلة التصنيع وينتج الصنف النهائي
 * بتكلفة = مجموع تكلفة المكونات، مع قيد محاسبي متوازن وتحديث AVCO.
 */
class AssemblyController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:assemblies.view')->only(['index', 'show']);
        $this->middleware('can:assemblies.create')->only(['create', 'store']);
    }

    public function index()
    {
        $assemblies = Assembly::with('product', 'warehouse', 'user')
            ->latest('assembly_date')->latest('id')
            ->get();
        $currency = Setting::get('currency_symbol', 'ج.م');

        return view('assemblies.index', compact('assemblies', 'currency'));
    }

    public function create()
    {
        // الأصناف التي لها معادلة تصنيع فقط
        $products = Product::whereHas('components')
            ->with('components.component')
            ->orderBy('name')
            ->get();

        $warehouses = \App\Models\Warehouse::where('is_active', true)->orderBy('name')->get();
        $currency   = Setting::get('currency_symbol', 'ج.م');

        return view('assemblies.create', compact('products', 'warehouses', 'currency'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id'    => 'required|exists:products,id',
            'warehouse_id'  => 'required|exists:warehouses,id',
            'quantity'      => 'required|numeric|min:0.001',
            'assembly_date' => 'required|date',
            'notes'         => 'nullable|string|max:1000',
        ]);

        $product = Product::with('components.component')->findOrFail($data['product_id']);

        if ($product->components->isEmpty()) {
            return back()->withInput()->with('error', 'هذا الصنف بلا معادلة تصنيع — عرّف مكوناته أولاً من بطاقة الصنف');
        }
        if ($product->isService()) {
            return back()->withInput()->with('error', 'لا يمكن تصنيع صنف من نوع خدمة');
        }

        $qty           = (float) $data['quantity'];
        $warehouseId   = (int) $data['warehouse_id'];
        $allowNegStock = (bool) Setting::get('allow_negative_stock', 0);

        DB::beginTransaction();
        try {
            // قفل أرصدة المكونات لمنع السحب المتزامن
            $componentIds = $product->components->pluck('component_id')->all();
            $levels = StockLevel::where('warehouse_id', $warehouseId)
                ->whereIn('product_id', $componentIds)
                ->lockForUpdate()->get()->keyBy('product_id');

            $components = Product::whereIn('id', $componentIds)->lockForUpdate()->get()->keyBy('id');

            // ── فحص توافر المكونات ──────────────────────────────────────────
            if (!$allowNegStock) {
                foreach ($product->components as $comp) {
                    $needed    = round($qty * (float) $comp->quantity, 4);
                    $available = isset($levels[$comp->component_id])
                        ? (float) $levels[$comp->component_id]->quantity
                        : 0.0;
                    if ($available < $needed) {
                        DB::rollBack();
                        return back()->withInput()->with('error',
                            'المكوّن "' . $components[$comp->component_id]->name . '" غير متوفر'
                            . ' (المطلوب: ' . number_format($needed, 2)
                            . ' / المتاح: ' . number_format($available, 2) . ')');
                    }
                }
            }

            // ── إنشاء المستند وحساب التكلفة ─────────────────────────────────
            $assembly = Assembly::create([
                'assembly_date' => $data['assembly_date'],
                'product_id'    => $product->id,
                'warehouse_id'  => $warehouseId,
                'quantity'      => $qty,
                'notes'         => $data['notes'] ?? null,
                'user_id'       => auth()->id(),
            ]);

            $totalCost = 0.0;
            foreach ($product->components as $comp) {
                $component  = $components[$comp->component_id];
                $usedQty    = round($qty * (float) $comp->quantity, 4);
                $unitCost   = (float) $component->cost_price;
                $lineCost   = round($usedQty * $unitCost, 2);
                $totalCost += $lineCost;

                AssemblyItem::create([
                    'assembly_id'  => $assembly->id,
                    'component_id' => $component->id,
                    'quantity'     => $usedQty,
                    'unit_cost'    => $unitCost,
                    'total_cost'   => $lineCost,
                ]);

                // حركة صادر للمكوّن (سجل append-only)
                StockMovement::create([
                    'product_id'     => $component->id,
                    'warehouse_id'   => $warehouseId,
                    'reference_type' => Assembly::class,
                    'reference_id'   => $assembly->id,
                    'quantity'       => $usedQty,
                    'cost'           => $unitCost,
                    'movement_type'  => 'out',
                    'notes'          => 'استهلاك تصنيع — ' . $assembly->number,
                ]);
                WarehouseService::out($warehouseId, $component->id, $usedQty);
            }

            $totalCost = round($totalCost, 2);
            $unitCost  = $qty > 0 ? round($totalCost / $qty, 4) : 0;

            // ── AVCO للصنف المنتَج (IAS 2 — المتوسط المرجح) ─────────────────
            $finished = Product::lockForUpdate()->find($product->id);
            $oldQty   = max(0, (float) $finished->quantity);
            $oldCost  = (float) $finished->cost_price;
            $avco     = ($oldQty + $qty) > 0.0001
                ? round(($oldQty * $oldCost + $qty * $unitCost) / ($oldQty + $qty), 4)
                : $unitCost;

            if (abs($avco - $oldCost) > 0.0001) {
                CostPriceHistory::create([
                    'product_id'     => $finished->id,
                    'old_cost'       => $oldCost,
                    'new_cost'       => $avco,
                    'qty_received'   => $qty,
                    'method'         => 'avco',
                    'reference_type' => Assembly::class,
                    'reference_id'   => $assembly->id,
                    'changed_by'     => auth()->id(),
                    'notes'          => 'تصنيع ' . $assembly->number . ' — تكلفة الوحدة المنتجة: ' . number_format($unitCost, 4),
                ]);
                // تجاوز أحداث الموديل لتفادي تسجيل مزدوج في ProductCostObserver
                Product::where('id', $finished->id)->update(['cost_price' => $avco]);
            }

            // حركة وارد للصنف المنتَج
            StockMovement::create([
                'product_id'     => $product->id,
                'warehouse_id'   => $warehouseId,
                'reference_type' => Assembly::class,
                'reference_id'   => $assembly->id,
                'quantity'       => $qty,
                'cost'           => $unitCost,
                'movement_type'  => 'in',
                'notes'          => 'إنتاج تصنيع — ' . $assembly->number,
            ]);
            WarehouseService::in($warehouseId, $product->id, $qty);

            $assembly->update(['total_cost' => $totalCost, 'unit_cost' => $unitCost]);

            // ── القيد المحاسبي ──────────────────────────────────────────────
            (new LedgerPostingService())->postAssembly($assembly->fresh(['items.component', 'product', 'warehouse']));

            DB::commit();

            return redirect()->route('assemblies.show', $assembly)
                ->with('success', 'تم تنفيذ التصنيع وترحيل القيد — ' . $assembly->number);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'فشل التصنيع: ' . $e->getMessage());
        }
    }

    public function show(Assembly $assembly)
    {
        $assembly->load('product', 'warehouse', 'user', 'items.component');
        $currency = Setting::get('currency_symbol', 'ج.م');

        $journalEntry = \App\Models\JournalEntry::with('lines.account')
            ->where('source_type', Assembly::class)
            ->where('source_id', $assembly->id)
            ->first();

        return view('assemblies.show', compact('assembly', 'currency', 'journalEntry'));
    }
}
