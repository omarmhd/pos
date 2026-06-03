<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\StockLevel;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Central service for all multi-warehouse stock-level operations.
 *
 * Philosophy:
 *   - products.quantity  = denormalized TOTAL across ALL warehouses (fast read, backward-compat)
 *   - stock_levels       = per-warehouse breakdown (accurate, for multi-location reporting)
 *
 * Both are updated atomically in every operation.
 * If a company has 1 warehouse: behaviour is identical to before this module was added.
 */
class WarehouseService
{
    // ── Default warehouse resolution ────────────────────────────────────────

    /**
     * Resolve the active default warehouse.
     * Order: setting → is_default flag → any active warehouse → exception.
     */
    public static function getDefault(): Warehouse
    {
        $settingId = (int) Setting::get('default_warehouse_id', 0);

        if ($settingId) {
            $wh = Warehouse::where('id', $settingId)->where('is_active', true)->first();
            if ($wh) {
                return $wh;
            }
        }

        $wh = Warehouse::where('is_default', true)->where('is_active', true)->first();
        if ($wh) {
            return $wh;
        }

        $wh = Warehouse::where('is_active', true)->first();
        if ($wh) {
            return $wh;
        }

        throw new RuntimeException('لا يوجد مخزن نشط في النظام. يرجى إنشاء مخزن أولاً من إعدادات المخازن.');
    }

    /**
     * Resolve warehouse for an authenticated user.
     *
     * Priority chain (matches international retail standards):
     *   1. user.pos_terminal.warehouse_id  ← the terminal's assigned location
     *      (e.g., cashier at display floor → WH-FLOOR)
     *   2. user.branch.default_warehouse   ← branch fallback
     *   3. system default_warehouse_id     ← global fallback
     *
     * This is how SAP/Oracle link each POS terminal to its physical location.
     */
    public static function getForUser(?\App\Models\User $user = null): Warehouse
    {
        $user = $user ?? auth()->user();

        // Priority 1: POS terminal's assigned warehouse
        if ($user && $user->pos_terminal_id) {
            $wh = \App\Models\PosTerminal::where('id', $user->pos_terminal_id)
                ->where('is_active', true)
                ->value('warehouse_id');
            if ($wh) {
                $warehouse = Warehouse::where('id', $wh)->where('is_active', true)->first();
                if ($warehouse) {
                    return $warehouse;
                }
            }
        }

        // Priority 2: User's branch default warehouse
        if ($user && $user->branch_id) {
            $wh = $user->branch?->defaultWarehouse();
            if ($wh) {
                return $wh;
            }
        }

        // Priority 3: System default
        return static::getDefault();
    }

    /**
     * Resolve a warehouse_id, falling back to default.
     * Returns an int (never null) — safe to store in DB.
     */
    public static function resolveId(?int $warehouseId): int
    {
        if ($warehouseId) {
            return $warehouseId;
        }
        return static::getDefault()->id;
    }

    // ── Stock level mutations (Single Source of Truth) ──────────────────────
    //
    // ARCHITECTURE CONTRACT:
    //   WarehouseService::in/out are the ONLY methods that update both
    //   stock_levels AND products.quantity. Model boots do NOT touch
    //   products.quantity directly — that column is now a CACHE derived
    //   from SUM(stock_levels.quantity) per product.
    //
    //   products.quantity = SUM(stock_levels.quantity WHERE product_id = X)
    //
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Record an inbound quantity. Updates stock_levels atomically,
     * then recomputes products.quantity from the SUM of all warehouses.
     */
    public static function in(int $warehouseId, int $productId, float $qty): void
    {
        if ($qty <= 0) {
            return;
        }

        // 1. Atomic upsert into stock_levels
        DB::statement('
            INSERT INTO stock_levels (warehouse_id, product_id, quantity, min_quantity, created_at, updated_at)
            VALUES (?, ?, ?, 5, NOW(), NOW())
            ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity), updated_at = NOW()
        ', [$warehouseId, $productId, $qty]);

        // 2. Recompute products.quantity as SUM across all warehouses (cache)
        static::recomputeProductTotal($productId);
    }

    /**
     * Record an outbound quantity. Updates stock_levels atomically,
     * then recomputes products.quantity from the SUM of all warehouses.
     *
     * Safety contract: the caller MUST hold lockForUpdate on stock_levels
     * before calling this (see PosController, PurchaseReturnController).
     */
    public static function out(int $warehouseId, int $productId, float $qty): void
    {
        if ($qty <= 0) {
            return;
        }

        // Ensure the row exists
        DB::statement('
            INSERT IGNORE INTO stock_levels (warehouse_id, product_id, quantity, min_quantity, created_at, updated_at)
            VALUES (?, ?, 0, 5, NOW(), NOW())
        ', [$warehouseId, $productId]);

        // Atomic decrement
        DB::statement('
            UPDATE stock_levels
            SET quantity = quantity - ?, updated_at = NOW()
            WHERE warehouse_id = ? AND product_id = ?
        ', [$qty, $warehouseId, $productId]);

        // Recompute products.quantity from SUM
        static::recomputeProductTotal($productId);
    }

    /**
     * Recompute products.quantity = SUM(stock_levels.quantity) for a product.
     * Called after every in/out operation — keeps products.quantity as a cache.
     *
     * Runs a single UPDATE with a correlated subquery — atomic and lock-safe
     * because it is always called within an existing DB::transaction().
     */
    private static function recomputeProductTotal(int $productId): void
    {
        DB::statement('
            UPDATE products
            SET quantity = (
                SELECT COALESCE(SUM(quantity), 0)
                FROM stock_levels
                WHERE product_id = ?
            )
            WHERE id = ?
        ', [$productId, $productId]);
    }

    // ── Per-warehouse queries ────────────────────────────────────────────────

    /**
     * Get quantity for a specific product in a specific warehouse.
     */
    public static function levelFor(int $warehouseId, int $productId): float
    {
        return (float) StockLevel::where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->value('quantity') ?? 0.0;
    }

    /**
     * Get all stock levels for a warehouse, with product info.
     * Used by the warehouse stock report.
     *
     * @return \Illuminate\Database\Eloquent\Collection<StockLevel>
     */
    public static function stockForWarehouse(int $warehouseId)
    {
        return StockLevel::with('product:id,name,barcode,unit,cost_price,selling_price')
            ->where('warehouse_id', $warehouseId)
            ->where('quantity', '!=', 0)
            ->orderByDesc('quantity')
            ->get();
    }

    // ── Sync / reconciliation ────────────────────────────────────────────────
    //
    // SOURCE OF TRUTH CONTRACT:
    // ─────────────────────────────────────────────────────────────────────────
    //  stock_levels(warehouse_id, product_id, quantity)
    //      = The AUTHORITATIVE per-warehouse balance.
    //      = Written atomically via WarehouseService::in/out.
    //      = Used for availability checks in PosController (with lockForUpdate).
    //
    //  products.quantity
    //      = Denormalized TOTAL across all warehouses.
    //      = Written by model boot() hooks (SaleItem, PurchaseItem, etc.).
    //      = Used for global low-stock alerts and backward-compat code.
    //      = MUST equal SUM(stock_levels.quantity) for the product.
    //
    //  Sync guarantee:
    //      Both are updated within the SAME DB::transaction.
    //      If the transaction rolls back, both roll back together.
    //      Drift is only possible if someone calls Product::update(['quantity'=>X])
    //      directly — which should be avoided outside WarehouseService/model boots.
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Verify consistency: returns products where products.quantity ≠ SUM(stock_levels).
     * Run via artisan tinker to check system health:
     *   WarehouseService::auditDrift();
     *
     * @return array<int, array{product_id: int, name: string, products_qty: float, levels_sum: float, diff: float}>
     */
    public static function auditDrift(): array
    {
        $levelsSum = DB::table('stock_levels')
            ->selectRaw('product_id, SUM(quantity) as total')
            ->groupBy('product_id')
            ->pluck('total', 'product_id');

        $drifts = [];

        \App\Models\Product::select('id', 'name', 'quantity')->each(function ($p) use ($levelsSum, &$drifts) {
            $total = (float) ($levelsSum[$p->id] ?? 0);
            $diff  = abs($total - (float) $p->quantity);
            if ($diff > 0.0001) {
                $drifts[] = [
                    'product_id'   => $p->id,
                    'name'         => $p->name,
                    'products_qty' => (float) $p->quantity,
                    'levels_sum'   => $total,
                    'diff'         => $diff,
                ];
            }
        });

        return $drifts;
    }

    /**
     * Recalculate products.quantity from the sum of all stock_levels.
     * Run after bulk imports or migrations to guarantee consistency.
     * Returns count of products updated.
     */
    public static function reconcileProductQuantities(): int
    {
        $counts = StockLevel::selectRaw('product_id, SUM(quantity) as total')
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        $updated = 0;
        \App\Models\Product::query()->each(function ($p) use ($counts, &$updated) {
            $total = (float) ($counts[$p->id]->total ?? 0);
            if (abs($total - (float) $p->quantity) > 0.0001) {
                $p->updateQuietly(['quantity' => $total]);
                $updated++;
            }
        });

        return $updated;
    }

    /**
     * Transfer stock from one warehouse to another.
     * Does NOT post a GL entry — use a manual voucher for that if required.
     */
    public static function transfer(
        int $fromWarehouseId,
        int $toWarehouseId,
        int $productId,
        float $qty
    ): void {
        if ($fromWarehouseId === $toWarehouseId) {
            throw new RuntimeException('المخزن المصدر والمخزن الوجهة متطابقان');
        }
        if ($qty <= 0) {
            throw new RuntimeException('الكمية يجب أن تكون أكبر من صفر');
        }

        $fromLevel = static::levelFor($fromWarehouseId, $productId);
        if ($fromLevel < $qty) {
            throw new RuntimeException(
                "الكمية المطلوبة ({$qty}) أكبر من الرصيد المتاح في المخزن المصدر ({$fromLevel})"
            );
        }

        // Atomic: decrement source, increment destination
        static::out($fromWarehouseId, $productId, $qty);
        static::in($toWarehouseId, $productId, $qty);
        // products.quantity stays the same (net zero move between warehouses)
    }
}
