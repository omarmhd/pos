<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerDeposit;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Services\LedgerPostingService;
use App\Services\PricingService;
use App\Services\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:sales.create')->only(['index', 'store']);
        $this->middleware('can:sales.view')->only(['receipt']);
    }

    public function index()
    {
        $allowNegStock = (bool) Setting::get('allow_negative_stock', 0);

        $products = Product::with('category')
            ->when(!$allowNegStock, fn($q) => $q->where('quantity', '>', 0))
            ->orderBy('name')
            ->limit(200)
            ->get();

        $customers = Customer::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'credit_limit']);

        $categories = Category::orderBy('name')->get(['id', 'name']);

        $posSettings = [
            'storeName'        => Setting::get('store_name',           'سوبر ماركت'),
            'storeAddress'     => Setting::get('store_address',        'شارع التحرير، المدينة'),
            'storePhone'       => Setting::get('store_phone',          '0123456789'),
            'storeTaxNumber'   => Setting::get('store_tax_number',     ''),
            'currencySymbol'   => Setting::get('currency_symbol',      'ج.م'),
            'vatEnabled'       => (bool) Setting::get('vat_enabled',   0),
            'vatRate'          => (float) Setting::get('vat_rate',     15),
            'vatInclusive'     => (bool) Setting::get('vat_inclusive', 0),
            'maxDiscountPct'   => (float) Setting::get('max_discount_percent', 100),
            'allowNegStock'    => $allowNegStock,
            'receiptFooter'    => Setting::get('receipt_footer',       'شكراً لزيارتكم'),
            'receiptWidthMm'   => (int) Setting::get('receipt_width_mm', 80),
            'creditEnabled'    => (bool) Setting::get('credit_sales_enabled', 1),
        ];

        return view('pos.index', compact('products', 'customers', 'categories', 'posSettings', 'allowNegStock'));
    }

    public function searchCustomers(Request $request)
    {
        $query = $request->input('q', '');
        $limit = 15;

        $customers = Customer::where('is_active', true)
            ->where(function($q) use ($query) {
                $q->where('name', 'like', "%$query%")
                  ->orWhere('phone', 'like', "%$query%")
                  ->orWhere('email', 'like', "%$query%");
            })
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'phone', 'email', 'credit_limit']);

        return response()->json($customers->map(fn($c) => [
            'id'              => $c->id,
            'name'            => $c->name,
            'phone'           => $c->phone,
            'credit_limit'    => (float) $c->credit_limit,
            'deposit_balance' => round($c->depositBalance(), 2),
            'price_list_id'   => $c->price_list_id,
            'price_list_name' => $c->priceList?->name,
        ]));
    }

    /**
     * AJAX: resolve prices for a list of product IDs under a given customer/price_list context.
     * Called by POS JS when the customer changes, to refresh cart prices.
     *
     * POST /pos/resolve-prices
     * Body: { customer_id: int|null, product_ids: int[] }
     * Returns: { prices: { [product_id]: float } }
     */
    public function resolvePrices(Request $request)
    {
        $customerId = $request->input('customer_id');
        $productIds = (array) $request->input('product_ids', []);

        $customer  = $customerId ? Customer::find($customerId) : null;
        $priceList = PricingService::resolveList($customer, auth()->user());

        $prices = PricingService::getPricesForProducts(
            array_map('intval', $productIds),
            $priceList
        );

        return response()->json([
            'prices'          => $prices,
            'price_list_name' => $priceList?->name ?? 'الافتراضي',
            'price_list_type' => $priceList?->type ?? 'retail',
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|numeric|min:0.001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'subtotal'           => 'required|numeric|min:0',
            'discount'           => 'nullable|numeric|min:0',
            'tax'                => 'nullable|numeric|min:0',
            'total_amount'       => 'required|numeric|min:0',
            'is_credit'          => 'nullable|boolean',
            'customer_id'        => 'required_if:is_credit,true|nullable|exists:customers,id',
            'payment_method'     => 'required_unless:is_credit,true|nullable|in:cash,card,mobile_wallet,deposit_balance',
            'paid_amount'        => 'required|numeric|min:0',
            'balance_used'       => 'nullable|numeric|min:0',
        ]);

        // ── Load settings (authoritative for calculation) ──────────────────
        $vatEnabled    = (bool) Setting::get('vat_enabled',           0);
        $vatRate       = (float) Setting::get('vat_rate',             15);
        $vatInclusive  = (bool) Setting::get('vat_inclusive',         0);
        $allowNegStock = (bool) Setting::get('allow_negative_stock',  0);
        $maxDiscPct    = (float) Setting::get('max_discount_percent', 100);

        // ── Resolve price list for this transaction ─────────────────────────
        $customer  = $request->customer_id ? Customer::find($request->customer_id) : null;
        $priceList = PricingService::resolveList($customer, auth()->user());

        // Build server-authoritative price map for all items
        $productIds    = collect($request->items)->pluck('product_id')->map('intval')->all();
        $serverPrices  = PricingService::getPricesForProducts($productIds, $priceList);

        $isCredit    = (bool) $request->input('is_credit', false);
        $subtotal    = round((float) $request->subtotal, 2);
        $discount    = round((float) ($request->discount ?? 0), 2);
        $balanceUsed = round((float) ($request->balance_used ?? 0), 2);

        // ── Recalculate subtotal using server-authoritative prices ───────────
        // This prevents clients from manipulating unit prices on the frontend.
        $serverSubtotal = 0;
        foreach ($request->items as $item) {
            $authorizedPrice = $serverPrices[(int) $item['product_id']] ?? (float) $item['unit_price'];
            $serverSubtotal += $item['quantity'] * $authorizedPrice;
        }
        $serverSubtotal = round($serverSubtotal, 2);

        // Accept if client's subtotal matches (within 1 fils rounding tolerance)
        // If mismatch → use server value (protects against price tampering)
        if (abs($serverSubtotal - $subtotal) > 0.01) {
            $subtotal = $serverSubtotal;
        }

        // ── Validate discount ceiling ──────────────────────────────────────
        if ($maxDiscPct < 100 && $subtotal > 0) {
            $discPct = ($discount / $subtotal) * 100;
            if ($discPct > $maxDiscPct + 0.005) {
                return response()->json([
                    'error' => 'الخصم يتجاوز الحد المسموح به (' . number_format($maxDiscPct, 0) . '% كحد أقصى)',
                ], 422);
            }
        }

        // ── Server-side VAT calculation (overrides frontend value) ─────────
        $afterDiscount = max(0.0, $subtotal - $discount);
        if ($vatEnabled && !$vatInclusive) {
            $tax = round($afterDiscount * $vatRate / 100, 2);
        } elseif ($vatEnabled && $vatInclusive) {
            $tax = round($afterDiscount - $afterDiscount / (1 + $vatRate / 100), 2);
        } else {
            $tax = 0.0;
        }
        $totalAmount = round($afterDiscount + $tax, 2);

        DB::beginTransaction();
        try {
            // Resolve warehouse for this sale (user's branch → system default)
            $warehouseId = WarehouseService::getForUser(auth()->user())->id;

            $productIds = collect($request->items)->pluck('product_id')->unique()->values()->all();

            // ── CRITICAL: Lock order must be consistent to prevent deadlocks ──
            // Always lock stock_levels FIRST, then products (consistent order = no deadlock)
            $stockLevels = \App\Models\StockLevel::where('warehouse_id', $warehouseId)
                ->whereIn('product_id', $productIds)
                ->lockForUpdate()   // ← ADDED: prevents overselling in concurrent requests
                ->get()
                ->keyBy('product_id');

            $products = Product::whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');

            foreach ($request->items as $item) {
                $product = $products->get($item['product_id']);
                if (!$product) {
                    return response()->json(['error' => 'منتج غير موجود: #' . $item['product_id']], 400);
                }
                if (!$allowNegStock) {
                    // Check per-warehouse stock; fall back to global if no stock_level record yet
                    $available = isset($stockLevels[$item['product_id']])
                        ? (float) $stockLevels[$item['product_id']]->quantity
                        : (float) $product->quantity;

                    if ($available < $item['quantity']) {
                        return response()->json([
                            'error' => 'المنتج "' . $product->name . '" غير متوفر بالكمية المطلوبة'
                                     . ' (المتاح في المخزن: ' . number_format($available, 2) . ')',
                        ], 400);
                    }
                }
            }

            // ── Validate deposit balance if used ──────────────────────────
            if ($balanceUsed > 0) {
                if (!$request->customer_id) {
                    return response()->json(['error' => 'يجب تحديد العميل لاستخدام رصيد الإيداع'], 422);
                }
                $depositCustomer = Customer::find($request->customer_id);
                $availableBalance = $depositCustomer ? $depositCustomer->depositBalance() : 0;
                if ($balanceUsed > $availableBalance + 0.005) {
                    return response()->json([
                        'error' => 'رصيد الإيداع المتاح (' . number_format($availableBalance, 2) . ') أقل من المبلغ المطلوب (' . number_format($balanceUsed, 2) . ')',
                    ], 422);
                }
            }

            $paidAmount   = $isCredit ? 0 : (float) $request->paid_amount;
            $changeAmount = $isCredit ? 0 : max(0, $paidAmount - ($totalAmount - $balanceUsed));

            // Validate cash paid + balance_used >= total for cash sales
            if (!$isCredit && ($paidAmount + $balanceUsed) < $totalAmount - 0.005) {
                return response()->json(['error' => 'المبلغ المدفوع (نقدي + رصيد) أقل من الإجمالي المستحق'], 422);
            }

            if ($isCredit && $request->customer_id) {
                $customer       = Customer::findOrFail($request->customer_id);
                $currentBalance = $customer->outstandingBalance();
                $newBalance     = $currentBalance + $totalAmount;
                if ($customer->credit_limit > 0 && $newBalance > $customer->credit_limit) {
                    return response()->json([
                        'error' => 'تجاوز حد الائتمان للعميل ' . $customer->name .
                                   ' (الحد: ' . number_format($customer->credit_limit, 2) .
                                   ' / الرصيد الحالي: ' . number_format($currentBalance, 2) . ')',
                    ], 422);
                }
            }

            $sale = Sale::create([
                'user_id'        => auth()->id(),
                'customer_id'    => ($isCredit || $balanceUsed > 0) ? $request->customer_id : null,
                'is_credit'      => $isCredit,
                'warehouse_id'   => $warehouseId,
                'subtotal'       => $subtotal,
                'discount'       => $discount,
                'tax'            => $tax,
                'total_amount'   => $totalAmount,
                'payment_method' => $isCredit ? 'cash' : $request->payment_method,
                'paid_amount'    => $paidAmount,
                'balance_used'   => $balanceUsed,
                'change_amount'  => $changeAmount,
            ]);

            foreach ($request->items as $item) {
                $product = $products->get($item['product_id']);
                // Use server-authoritative price (prevents frontend price manipulation)
                $unitPrice = $serverPrices[(int) $item['product_id']] ?? (float) $item['unit_price'];
                SaleItem::create([
                    'sale_id'     => $sale->id,
                    'product_id'  => $item['product_id'],
                    'quantity'    => $item['quantity'],
                    'unit_price'  => $unitPrice,
                    'cost_price'  => $product->cost_price,
                    'total_price' => round($item['quantity'] * $unitPrice, 2),
                ]);
                // SaleItem::created() boot handles products.quantity decrement.
                // Update per-warehouse stock_level here.
                WarehouseService::out($warehouseId, $item['product_id'], $item['quantity']);
            }

            (new LedgerPostingService())->postSale($sale->load('items', 'customer'));

            DB::commit();

            $sale->load('items.product', 'customer', 'user');
            $payLabel = $isCredit ? 'آجل' : match ($sale->payment_method) {
                'cash'            => 'نقدي',
                'card'            => 'بطاقة بنكية',
                'mobile_wallet'   => 'محفظة إلكترونية',
                'deposit_balance' => 'رصيد إيداع',
                default           => $sale->payment_method,
            };

            return response()->json([
                'success'        => true,
                'sale_id'        => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'change_amount'  => $changeAmount,
                'receipt'        => [
                    'invoice_number' => $sale->invoice_number,
                    'date'           => $sale->created_at->format('Y-m-d H:i'),
                    'cashier'        => $sale->user->name,
                    'payment_method' => $payLabel,
                    'customer'       => $sale->customer?->name ?? '',
                    'is_credit'      => $isCredit,
                    'items'          => $sale->items->map(fn($i) => [
                        'name'  => $i->product->name,
                        'qty'   => $i->quantity,
                        'unit'  => $i->product->unit ?? 'قطعة',
                        'price' => number_format($i->unit_price, 2),
                        'total' => number_format($i->total_price, 2),
                    ])->toArray(),
                    'subtotal'    => number_format($sale->subtotal, 2),
                    'discount'    => number_format($sale->discount, 2),
                    'has_discount'=> $sale->discount > 0,
                    'tax'         => number_format($sale->tax, 2),
                    'has_tax'     => $sale->tax > 0,
                    'tax_rate'    => $vatRate,
                    'total'           => number_format($sale->total_amount, 2),
                    'paid'            => number_format($paidAmount, 2),
                    'balance_used'    => number_format($balanceUsed, 2),
                    'has_balance'     => $balanceUsed > 0,
                    'change'          => number_format($changeAmount, 2),
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function receipt(int $id)
    {
        $sale = Sale::with('user', 'items.product', 'customer')->findOrFail($id);
        return view('pos.receipt', compact('sale'));
    }
}
