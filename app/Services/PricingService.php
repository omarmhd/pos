<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Pricing resolution service.
 *
 * Priority chain (highest → lowest):
 *   1. Customer's assigned price_list
 *   2. Branch's assigned price_list (user's branch)
 *   3. System default price_list
 *   4. products.selling_price (ultimate fallback — no price_list needed)
 *
 * A branch can serve BOTH retail and wholesale customers simultaneously.
 * The pricing decision is always customer-driven, not branch-driven.
 */
class PricingService
{
    // ── Price List Resolution ────────────────────────────────────────────────

    /**
     * Resolve which price list to use for a given customer + user context.
     *
     * @param  Customer|null $customer  The customer being served (null = walk-in/cash)
     * @param  User|null     $user      The logged-in user (to derive branch)
     */
    public static function resolveList(?Customer $customer = null, ?User $user = null): ?PriceList
    {
        // 1. Customer has a specific price list
        if ($customer && $customer->price_list_id) {
            return $customer->priceList;
        }

        // 2. User's branch has a default price list
        $user = $user ?? auth()->user();
        if ($user && $user->branch_id) {
            $branch = $user->branch ?? Branch::find($user->branch_id);
            if ($branch && $branch->price_list_id) {
                return $branch->priceList;
            }
        }

        // 3. System default price list
        $defaultId = (int) Setting::get('default_price_list_id', 0);
        if ($defaultId) {
            return PriceList::find($defaultId);
        }

        // 4. Any active price list
        return PriceList::where('is_default', true)->where('is_active', true)->first();
    }

    // ── Price Resolution for a Single Product ───────────────────────────────

    /**
     * Get the selling price for a product under a given price list.
     * Falls back through the chain until products.selling_price.
     *
     * @param  int|Product   $product      Product ID or model
     * @param  int|PriceList|null $priceList  Price list ID, model, or null
     * @param  float|null    $fallback     Explicit fallback (default: product.selling_price)
     */
    public static function getPrice(
        int|Product $product,
        int|PriceList|null $priceList,
        ?float $fallback = null
    ): float {
        $product = $product instanceof Product ? $product : Product::find($product);
        if (!$product) {
            return (float) ($fallback ?? 0);
        }

        $baseFallback = (float) ($fallback ?? $product->selling_price);

        if (!$priceList) {
            return $baseFallback;
        }

        $priceListId = $priceList instanceof PriceList ? $priceList->id : (int) $priceList;

        $entry = ProductPrice::where('price_list_id', $priceListId)
            ->where('product_id', $product->id)
            ->first();

        return $entry ? (float) $entry->selling_price : $baseFallback;
    }

    // ── Batch Resolution (for POS product catalog) ───────────────────────────

    /**
     * Return a map of [product_id => selling_price] for an array of product IDs
     * under a given price list. Efficiently loads in one query.
     *
     * @param  int[]              $productIds
     * @param  int|PriceList|null $priceList
     * @return array<int, float>   [product_id => price]
     */
    public static function getPricesForProducts(
        array $productIds,
        int|PriceList|null $priceList
    ): array {
        if (empty($productIds)) {
            return [];
        }

        // Load base prices from products table
        $baseMap = Product::whereIn('id', $productIds)
            ->pluck('selling_price', 'id')
            ->map(fn($p) => (float) $p)
            ->toArray();

        if (!$priceList) {
            return $baseMap;
        }

        $priceListId = $priceList instanceof PriceList ? $priceList->id : (int) $priceList;

        // Override with price_list specific prices
        ProductPrice::where('price_list_id', $priceListId)
            ->whereIn('product_id', $productIds)
            ->get(['product_id', 'selling_price'])
            ->each(function ($pp) use (&$baseMap) {
                $baseMap[$pp->product_id] = (float) $pp->selling_price;
            });

        return $baseMap;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Get all price list entries for a product (for admin price editing UI).
     *
     * @return Collection<PriceList> with injected pivot price
     */
    public static function getPriceListsForProduct(int $productId): Collection
    {
        $product = Product::findOrFail($productId);

        // Load all active price lists
        $lists = PriceList::where('is_active', true)->orderBy('name')->get();

        // Load existing entries for this product
        $existing = ProductPrice::where('product_id', $productId)
            ->get()
            ->keyBy('price_list_id');

        // Merge: inject price_entry (or null) into each list
        return $lists->map(function (PriceList $list) use ($existing, $product) {
            $entry = $existing->get($list->id);
            $list->price_entry        = $entry;
            $list->resolved_price     = $entry ? (float) $entry->selling_price : (float) $product->selling_price;
            $list->uses_default_price = !$entry;
            return $list;
        });
    }

    /**
     * Bulk upsert prices for a product from a form submission.
     * Expects array like: ['price_list_id' => ['price' => X, 'min_qty' => Y], ...]
     */
    public static function savePricesForProduct(int $productId, array $listPrices): void
    {
        foreach ($listPrices as $priceListId => $data) {
            $price = isset($data['price']) && $data['price'] !== '' ? (float) $data['price'] : null;

            if ($price === null || $price < 0) {
                // Empty = "use default" → delete any existing override
                ProductPrice::where('price_list_id', $priceListId)
                    ->where('product_id', $productId)
                    ->delete();
                continue;
            }

            ProductPrice::updateOrCreate(
                ['price_list_id' => $priceListId, 'product_id' => $productId],
                [
                    'selling_price' => $price,
                    'min_quantity'  => max(1, (float) ($data['min_qty'] ?? 1)),
                ]
            );
        }
    }
}
