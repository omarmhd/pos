<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\InventorySession;
use App\Models\InventorySessionItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventorySessionController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:inventory.count');
    }

    public function index()
    {
        $branchId     = $this->effectiveBranchId(request());
        $branchLocked = $this->isBranchLocked();
        $warehouseId  = request()->filled('warehouse_id') ? (int) request('warehouse_id') : null;

        $sessions = InventorySession::with('createdBy:id,name', 'warehouse:id,name,branch_id', 'warehouse.branch:id,name')
            ->withCount([
                'items as total_items',
                'items as counted_items'  => fn($q) => $q->whereNotNull('counted_quantity'),
                'items as variance_items' => fn($q) => $q->whereNotNull('counted_quantity')
                                                         ->where('difference', '!=', 0),
            ])
            ->when($branchId, fn($q) => $q->whereHas('warehouse', fn($wq) => $wq->where('branch_id', $branchId)))
            ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))
            ->orderByDesc('created_at')
            ->paginate(20);

        $branches   = \App\Models\Branch::where('is_active', true)->orderBy('name')->get(['id','name','code']);
        $warehouses = \App\Models\Warehouse::where('is_active', true)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->orderBy('name')->get(['id','name','branch_id']);

        return view('inventory.sessions.index', compact('sessions', 'branches', 'branchId', 'branchLocked', 'warehouses', 'warehouseId'));
    }

    public function create()
    {
        $categories = Category::orderBy('name')->get(['id', 'name']);
        $warehouses = \App\Models\Warehouse::where('is_active', true)
            ->with('branch:id,name')
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'branch_id', 'is_default']);
        $defaultWarehouseId = \App\Services\WarehouseService::getDefault()->id;
        return view('inventory.sessions.create', compact('categories', 'warehouses', 'defaultWarehouseId'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'        => 'required|string|max:200',
            'warehouse_id' => 'required|exists:warehouses,id',
            'category_id'  => 'nullable|exists:categories,id',
            'notes'        => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($data) {
            $warehouseId = (int) $data['warehouse_id'];

            $session = InventorySession::create([
                'title'        => $data['title'],
                'warehouse_id' => $warehouseId,
                'category_id'  => $data['category_id'] ?? null,
                'notes'        => $data['notes'] ?? null,
                'status'       => 'in_progress',
                'started_at'   => now(),
                'created_by'   => Auth::id(),
            ]);

            // Snapshot quantities from THIS warehouse's stock_levels (not global products.quantity)
            // IAS 2: Physical count must reflect the specific location being counted.
            $query = Product::query();
            if ($session->category_id) {
                $query->where('category_id', $session->category_id);
            }
            $products = $query->get(['id', 'quantity', 'cost_price']);

            // Pre-load stock levels for this specific warehouse
            $stockLevels = \App\Models\StockLevel::where('warehouse_id', $warehouseId)
                ->whereIn('product_id', $products->pluck('id'))
                ->pluck('quantity', 'product_id');

            $items = $products->map(fn($p) => [
                'session_id'       => $session->id,
                'product_id'       => $p->id,
                // system_quantity from THIS warehouse, fallback to 0 if not found
                'system_quantity'  => (float) ($stockLevels[$p->id] ?? 0),
                'counted_quantity' => null,
                'difference'       => 0,
                'cost_per_unit'    => $p->cost_price,
                'created_at'       => now(),
                'updated_at'       => now(),
            ])->all();

            foreach (array_chunk($items, 200) as $chunk) {
                DB::table('inventory_session_items')->insert($chunk);
            }

            return $session;
        });

        return redirect()->route('inventory.sessions.index')
            ->with('success', 'تم إنشاء جلسة الجرد — ابدأ بإدخال الكميات الفعلية');
    }

    public function show(Request $request, InventorySession $session)
    {
        $session->load('createdBy:id,name', 'approvedBy:id,name');

        // Stats via single DB aggregate — never loads all rows into PHP memory
        $stats = $session->items()
            ->selectRaw("
                COUNT(*) as total,
                SUM(counted_quantity IS NOT NULL) as counted,
                SUM(counted_quantity IS NOT NULL AND difference != 0) as with_diff,
                SUM(CASE WHEN difference < 0 THEN ABS(difference * cost_per_unit) ELSE 0 END) as shortage_val,
                SUM(CASE WHEN difference > 0 THEN difference * cost_per_unit ELSE 0 END) as surplus_val
            ")
            ->first();

        // Paginated items — select only needed columns
        $items = $session->items()
            ->select(['id','session_id','product_id','system_quantity',
                      'counted_quantity','difference','cost_per_unit','notes'])
            ->with([
                'product:id,name,barcode,unit,category_id',
                'product.category:id,name',
            ])
            ->when($request->filled('category'), fn($q) =>
                $q->whereHas('product', fn($p) => $p->where('category_id', $request->category))
            )
            ->when($request->filled('filter'), function ($q) use ($request) {
                match ($request->filter) {
                    'uncounted' => $q->whereNull('counted_quantity'),
                    'shortage'  => $q->where('difference', '<', 0),
                    'surplus'   => $q->where('difference', '>', 0),
                    default     => null,
                };
            })
            ->orderBy('product_id')
            ->paginate(100)
            ->withQueryString();

        $categories = \App\Models\Category::orderBy('name')->get(['id','name']);

        return view('inventory.sessions.show', compact('session', 'items', 'stats', 'categories'));
    }

    /** AJAX: update counted quantity for one item */
    public function updateItem(Request $request, InventorySession $session)
    {
        abort_if($session->status !== 'in_progress', 403, 'الجلسة غير قابلة للتعديل');

        $data = $request->validate([
            'product_id'      => 'required|exists:products,id',
            'counted_quantity'=> 'required|numeric|min:0',
        ]);

        $item = InventorySessionItem::where('session_id', $session->id)
            ->where('product_id', $data['product_id'])
            ->firstOrFail();

        $item->update([
            'counted_quantity' => $data['counted_quantity'],
            'difference'       => (float)$data['counted_quantity'] - (float)$item->system_quantity,
        ]);

        return response()->json([
            'difference'     => $item->difference,
            'variance_value' => $item->varianceValue(),
        ]);
    }

    /** AJAX: lookup product by barcode for the session */
    public function scanBarcode(Request $request, InventorySession $session)
    {
        $barcode = $request->input('barcode', '');
        $item = $session->items()
            ->whereHas('product', fn($q) => $q->where('barcode', $barcode))
            ->with('product')
            ->first();

        if (!$item) {
            return response()->json(['error' => 'المنتج غير موجود في هذه الجلسة'], 404);
        }

        return response()->json([
            'product_id'      => $item->product_id,
            'name'            => $item->product->name,
            'system_quantity' => $item->system_quantity,
            'counted_quantity'=> $item->counted_quantity,
            'unit'            => $item->product->unit,
        ]);
    }

    /** Approve session: create adjustments + GL entry + update product quantities */
    public function approve(InventorySession $session)
    {
        abort_if($session->status !== 'in_progress', 403, 'الجلسة غير قابلة للاعتماد');

        $uncounted = $session->items()->whereNull('counted_quantity')->count();
        if ($uncounted > 0) {
            return back()->with('error', "لا يزال {$uncounted} منتج لم يُعدّ — أكمل الجرد أولاً أو اعتمده جزئياً");
        }

        DB::transaction(function () use ($session) {
            // Load only columns we need — no product relationship needed here
            $items = $session->items()
                ->select(['id','session_id','product_id','system_quantity',
                          'counted_quantity','difference','cost_per_unit'])
                ->where('difference', '!=', 0)
                ->whereNotNull('counted_quantity')
                ->get();

            // Cache GL accounts & settings — lookup once, not per item
            $invCode      = Setting::get('account_inventory_code', '1300');
            $shortageCode = Setting::get('account_inventory_shortage_code', '6510');
            $surplusCode  = Setting::get('account_inventory_surplus_code',  '4100');
            $invAccount      = Account::where('code', $invCode)->first();
            $shortageAccount = Account::where('code', $shortageCode)->first();
            $surplusAccount  = Account::where('code', $surplusCode)->first();

            $now           = now();
            $userId        = Auth::id();
            $totalShortage = 0.0;
            $totalSurplus  = 0.0;
            $adjRows       = [];          // batch insert buffer
            $productUpdates = [];         // [product_id => counted_qty]

            foreach ($items as $item) {
                $amount = round(abs((float)$item->difference * (float)$item->cost_per_unit), 2);
                if ($amount < 0.001) continue;

                // Collect product quantity updates
                $productUpdates[$item->product_id] = $item->counted_quantity;

                // Collect adjustment rows — include warehouse_id from session
                $adjRows[] = [
                    'product_id'          => $item->product_id,
                    'warehouse_id'        => $session->warehouse_id,   // ← FIXED
                    'quantity_before'     => $item->system_quantity,
                    'quantity_after'      => $item->counted_quantity,
                    'quantity_change'     => $item->difference,
                    'cost_per_unit'       => $item->cost_per_unit,
                    'total_cost'          => $amount,
                    'reason'              => 'count_correction',
                    'notes'               => "جلسة جرد: {$session->reference}",
                    'inventory_session_id'=> $session->id,
                    'created_by'          => $userId,
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ];

                $item->difference < 0 ? $totalShortage += $amount : $totalSurplus += $amount;
            }

            // ── 1. Lock products before bulk update (prevent concurrent race conditions) ──
            $allProductIds = array_keys($productUpdates);
            if (!empty($allProductIds)) {
                \App\Models\Product::whereIn('id', $allProductIds)->lockForUpdate()->get();
            }

            // ── 2. Update stock_levels for THIS SESSION'S WAREHOUSE ──────────
            // IAS 2: The count updates inventory for the specific location counted.
            // We use session.warehouse_id (now properly stored), NOT the system default.
            $sessionWarehouseId = $session->warehouse_id
                ?? (int) \App\Models\Setting::get('default_warehouse_id');

            if ($sessionWarehouseId && !empty($productUpdates)) {
                foreach (array_chunk($productUpdates, 500, true) as $chunk) {
                    foreach ($chunk as $pid => $qty) {
                        // SET stock_levels[session_warehouse][product] = counted_quantity
                        DB::statement('
                            INSERT INTO stock_levels (warehouse_id, product_id, quantity, min_quantity, created_at, updated_at)
                            VALUES (?, ?, ?, 5, NOW(), NOW())
                            ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), updated_at = NOW()
                        ', [$sessionWarehouseId, $pid, $qty]);
                    }
                }

                // Recompute products.quantity = SUM(all warehouses stock_levels)
                // This preserves stock in OTHER warehouses untouched.
                $pidList = implode(',', array_map('intval', array_keys($productUpdates)));
                DB::statement("
                    UPDATE products p
                    SET p.quantity = (
                        SELECT COALESCE(SUM(sl.quantity), 0)
                        FROM stock_levels sl
                        WHERE sl.product_id = p.id
                    )
                    WHERE p.id IN ({$pidList})
                ");
            }

            // ── 2. Batch-insert adjustments ──
            foreach (array_chunk($adjRows, 500) as $chunk) {
                DB::table('inventory_adjustments')->insert($chunk);
            }

            // ── 3. Single consolidated GL journal entry ──
            $journalEntry = null;
            if ($totalShortage > 0 || $totalSurplus > 0) {
                \App\Services\PeriodLockService::assertOpen($now->toDateString());

                $journalEntry = JournalEntry::create([
                    'entry_date'  => $now->toDateString(),
                    'description' => "اعتماد جلسة الجرد {$session->reference}",
                    'source_type' => InventorySession::class,
                    'source_id'   => $session->id,
                    'user_id'     => $userId ?? 1,
                    'posted_at'   => $now,
                ]);

                $jeLines = [];
                if ($totalShortage > 0 && $shortageAccount && $invAccount) {
                    $jeLines[] = ['journal_entry_id' => $journalEntry->id, 'account_id' => $shortageAccount->id, 'debit' => $totalShortage, 'credit' => 0, 'line_description' => 'عجز مخزون', 'created_at' => $now, 'updated_at' => $now];
                    $jeLines[] = ['journal_entry_id' => $journalEntry->id, 'account_id' => $invAccount->id,      'debit' => 0, 'credit' => $totalShortage, 'line_description' => 'تسوية مخزون', 'created_at' => $now, 'updated_at' => $now];
                }
                if ($totalSurplus > 0 && $surplusAccount && $invAccount) {
                    $jeLines[] = ['journal_entry_id' => $journalEntry->id, 'account_id' => $invAccount->id,     'debit' => $totalSurplus, 'credit' => 0, 'line_description' => 'تسوية مخزون', 'created_at' => $now, 'updated_at' => $now];
                    $jeLines[] = ['journal_entry_id' => $journalEntry->id, 'account_id' => $surplusAccount->id, 'debit' => 0, 'credit' => $totalSurplus, 'line_description' => 'زيادة مخزون', 'created_at' => $now, 'updated_at' => $now];
                }
                if ($jeLines) {
                    DB::table('journal_entry_lines')->insert($jeLines);
                }

                // Single update to link adjustments → journal entry
                DB::table('inventory_adjustments')
                    ->where('inventory_session_id', $session->id)
                    ->update(['journal_entry_id' => $journalEntry->id]);
            }

            $session->update([
                'status'           => 'approved',
                'approved_at'      => $now,
                'approved_by'      => $userId,
                'journal_entry_id' => $journalEntry?->id,
            ]);
        });

        return redirect()->route('inventory.sessions.show', $session)
            ->with('success', 'تم اعتماد الجرد وإنشاء القيود المحاسبية بنجاح');
    }

    public function cancel(InventorySession $session)
    {
        abort_if(!in_array($session->status, ['draft','in_progress']), 403);
        $session->update(['status' => 'cancelled']);
        return redirect()->route('inventory.sessions.index')
            ->with('success', 'تم إلغاء جلسة الجرد');
    }
}
