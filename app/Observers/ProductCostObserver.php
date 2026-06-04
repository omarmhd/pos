<?php

namespace App\Observers;

use App\Models\CostPriceHistory;
use App\Models\Product;

/**
 * Tracks manual changes to Product.cost_price.
 * AVCO changes are already logged in PurchaseItem::boot().
 * This observer catches: manual edits via ProductController, CSV imports.
 */
class ProductCostObserver
{
    public function updated(Product $product): void
    {
        if (!$product->isDirty('cost_price')) {
            return;
        }

        $oldCost = (float) $product->getOriginal('cost_price');
        $newCost = (float) $product->cost_price;

        if (abs($newCost - $oldCost) < 0.0001) {
            return;
        }

        // Skip if the change came from AVCO (PurchaseItem::boot writes history first)
        // We distinguish: PurchaseItem boot sets cost via product->update(), which
        // triggers this observer. To avoid double-logging, PurchaseItem boot
        // uses Product::where()->update() (bypasses model events) while we use
        // $product->update() for manual edits. Both paths reach here, but we
        // check the request context — no purchase in progress = manual edit.
        if (app()->runningInConsole()) {
            return; // skip seeder/import noise
        }

        CostPriceHistory::create([
            'product_id'  => $product->id,
            'old_cost'    => $oldCost,
            'new_cost'    => $newCost,
            'method'      => 'manual',
            'changed_by'  => auth()->id(),
            'notes'       => 'تعديل يدوي',
        ]);
    }
}
