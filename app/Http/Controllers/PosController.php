<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CashShift;
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

        $products = Product::with('category', 'units')
            ->when(!$allowNegStock, fn($q) => $q->where(function ($qq) {
                // الخدمات والأصناف التجميعية لا مخزون لها — تظهر دائماً
                $qq->where('quantity', '>', 0)
                   ->orWhereIn('product_type', [Product::TYPE_SERVICE, Product::TYPE_BUNDLE]);
            }))
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

        $activeShift = CashShift::activeForUser(auth()->id());

        return view('pos.index', compact('products', 'customers', 'categories', 'posSettings', 'allowNegStock', 'activeShift'));
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
            'items'                   => 'required|array|min:1',
            'items.*.product_id'      => 'required|exists:products,id',
            'items.*.product_unit_id' => 'nullable|exists:product_units,id',
            'items.*.quantity'        => 'required|numeric|min:0.001',
            'items.*.unit_price'      => 'required|numeric|min:0',
            'subtotal'           => 'required|numeric|min:0',
            'discount'           => 'nullable|numeric|min:0',
            'tax'                => 'nullable|numeric|min:0',
            'total_amount'       => 'required|numeric|min:0',
            'is_credit'          => 'nullable|boolean',
            'customer_id'        => 'required_if:is_credit,true|nullable|exists:customers,id',
            'payment_method'     => 'required_unless:is_credit,true|nullable|in:cash,card,mobile_wallet,deposit_balance,mixed',
            'paid_amount'        => 'required|numeric|min:0',
            'balance_used'       => 'nullable|numeric|min:0',
            // ── تحسينات الأصيل: خصم نسبة، شامل الضريبة، مرجع مقاصة، دفع مختلط بالشيكات ──
            'discount_percent'   => 'nullable|numeric|min:0|max:100',
            'tax_inclusive'      => 'nullable|boolean',
            'setoff_ref'         => 'nullable|string|max:255',
            'cheque_amount'      => 'nullable|numeric|min:0',
            'cheque_ref'         => 'nullable|string|max:100',
            'cheque_bank'        => 'nullable|string|max:255',
            'cheque_due_date'    => 'nullable|date',
        ]);

        // ── Load settings (authoritative for calculation) ──────────────────
        $vatEnabled    = (bool) Setting::get('vat_enabled',           0);
        $vatRate       = (float) Setting::get('vat_rate',             15);
        $vatInclusive  = (bool) Setting::get('vat_inclusive',         0);
        $allowNegStock = (bool) Setting::get('allow_negative_stock',  0);
        $maxDiscPct    = (float) Setting::get('max_discount_percent', 100);

        // تجاوز "شامل الضريبة" لكل فاتورة (اختياري) — يبقى الإعداد العام إن لم يُرسل
        if ($request->has('tax_inclusive') && $request->input('tax_inclusive') !== null) {
            $vatInclusive = (bool) $request->input('tax_inclusive');
        }

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

        // ── Normalize lines: unit conversion + server-authoritative prices ──
        // كل سطر يُحوَّل للوحدة الرئيسية (baseQty) والسعر للوحدة الرئيسية.
        // هذا يمنع تلاعب الواجهة بالأسعار ويوحّد حسابات المخزون والتكلفة.
        $lineProductIds = collect($request->items)->pluck('product_id')->map('intval')->unique()->all();
        $lineProducts   = Product::with('components.component', 'units')
            ->whereIn('id', $lineProductIds)->get()->keyBy('id');

        $normalized = [];   // [{product, unit, factor, baseQty, basePrice, lineTotal, ...}]
        foreach ($request->items as $item) {
            $product = $lineProducts->get((int) $item['product_id']);
            if (!$product) {
                return response()->json(['error' => 'منتج غير موجود: #' . $item['product_id']], 400);
            }

            $unit   = null;
            $factor = 1.0;
            if (!empty($item['product_unit_id'])) {
                $unit = $product->units->firstWhere('id', (int) $item['product_unit_id']);
                if (!$unit) {
                    return response()->json(['error' => 'وحدة غير صحيحة للمنتج: ' . $product->name], 422);
                }
                $factor = (float) $unit->factor;
            }

            $qtyEntered = (float) $item['quantity'];          // بالوحدة المختارة
            $baseQty    = round($qtyEntered * $factor, 4);    // بالوحدة الرئيسية

            // السعر المعتمد من الخادم (للوحدة الرئيسية)
            $resolvedBase = (float) ($serverPrices[(int) $item['product_id']] ?? (float) $product->selling_price);
            if ($unit) {
                $unitPrice = $unit->selling_price !== null
                    ? (float) $unit->selling_price
                    : round($resolvedBase * $factor, 2);
                $basePrice = $factor > 0 ? round($unitPrice / $factor, 4) : $resolvedBase;
            } else {
                $basePrice = $resolvedBase;
            }

            $normalized[] = [
                'product'   => $product,
                'unit'      => $unit,
                'factor'    => $factor,
                'baseQty'   => $baseQty,
                'basePrice' => $basePrice,
                'lineTotal' => round($baseQty * $basePrice, 2),
            ];
        }

        $serverSubtotal = round(array_sum(array_column($normalized, 'lineTotal')), 2);

        // Accept if client's subtotal matches (within 1 fils rounding tolerance)
        // If mismatch → use server value (protects against price tampering)
        if (abs($serverSubtotal - $subtotal) > 0.01) {
            $subtotal = $serverSubtotal;
        }

        // خصم نسبة على مستوى الفاتورة (يُضاف لمبلغ الخصم قبل الضريبة)
        $discountPercent = round((float) ($request->discount_percent ?? 0), 2);
        if ($discountPercent > 0 && $subtotal > 0) {
            $discount = round($discount + $subtotal * $discountPercent / 100, 2);
        }
        // الدفع المختلط: جزء الشيكات
        $chequeAmount = round((float) ($request->cheque_amount ?? 0), 2);

        // ── Validate discount ceiling ──────────────────────────────────────
        if ($maxDiscPct < 100 && $subtotal > 0) {
            $discPct = ($discount / $subtotal) * 100;
            if ($discPct > $maxDiscPct + 0.005) {
                return response()->json([
                    'error' => 'الخصم يتجاوز الحد المسموح به (' . number_format($maxDiscPct, 0) . '% كحد أقصى)',
                ], 422);
            }
        }

        // ── Server-side VAT: per-line, per-product rate (ضريبة لكل صنف) ─────
        // الخصم على مستوى الفاتورة يُوزَّع نسبياً على السطور قبل احتساب الضريبة
        // (المعالجة الضريبية القياسية للخصومات).
        $tax = 0.0;
        foreach ($normalized as $k => $line) {
            $rate = $line['product']->effectiveVatRate();   // 0 إذا معفى أو الضريبة معطلة

            $discountShare = ($subtotal > 0 && $discount > 0)
                ? round($discount * $line['lineTotal'] / $subtotal, 4)
                : 0.0;
            $netLine = max(0.0, $line['lineTotal'] - $discountShare);

            if ($rate > 0 && $vatInclusive) {
                $lineVat = round($netLine - $netLine / (1 + $rate / 100), 2);
            } elseif ($rate > 0) {
                $lineVat = round($netLine * $rate / 100, 2);
            } else {
                $lineVat = 0.0;
            }

            $normalized[$k]['vatRate']   = $rate;
            $normalized[$k]['vatAmount'] = $lineVat;
            $tax += $lineVat;
        }
        $tax = round($tax, 2);

        $afterDiscount = max(0.0, $subtotal - $discount);
        $totalAmount   = $vatInclusive
            ? round($afterDiscount, 2)                       // الضريبة ضمن السعر
            : round($afterDiscount + $tax, 2);

        DB::beginTransaction();
        try {
            // Resolve warehouse for this sale (user's branch → system default)
            $warehouseId = WarehouseService::getForUser(auth()->user())->id;

            // ── حساب البونص + الاحتياج الفعلي من المخزون لكل صنف ──────────────
            // بضاعة: الاحتياج = الكمية الأساسية + البونص
            // تجميعي: الاحتياج يقع على المكونات (كمية الأب × كمية المكوّن)
            // خدمة: لا احتياج مخزني
            $requirements = [];   // [product_id => baseQty needed]
            foreach ($normalized as $k => $line) {
                $product = $line['product'];

                if ($product->isService()) {
                    $normalized[$k]['bonusQty'] = 0.0;
                    continue;
                }

                if ($product->isBundle()) {
                    $normalized[$k]['bonusQty'] = 0.0;
                    if ($product->components->isEmpty()) {
                        return response()->json(['error' => 'الصنف التجميعي "' . $product->name . '" بلا مكونات — عرّف مكوناته أولاً'], 422);
                    }
                    foreach ($product->components as $comp) {
                        $requirements[$comp->component_id] = ($requirements[$comp->component_id] ?? 0)
                            + $line['baseQty'] * (float) $comp->quantity;
                    }
                    continue;
                }

                // بضاعة: البونص (الكمية الإضافية) يُحتسب على الكمية الأساسية
                $bonus = $product->bonusFor($line['baseQty']);
                $normalized[$k]['bonusQty'] = $bonus;
                $requirements[$product->id] = ($requirements[$product->id] ?? 0)
                    + $line['baseQty'] + $bonus;
            }

            $stockProductIds = array_keys($requirements);

            // ── CRITICAL: Lock order must be consistent to prevent deadlocks ──
            // Always lock stock_levels FIRST, then products (consistent order = no deadlock)
            $stockLevels = \App\Models\StockLevel::where('warehouse_id', $warehouseId)
                ->whereIn('product_id', $stockProductIds)
                ->lockForUpdate()   // ← prevents overselling in concurrent requests
                ->get()
                ->keyBy('product_id');

            $products = Product::whereIn('id', $stockProductIds)->lockForUpdate()->get()->keyBy('id');

            if (!$allowNegStock) {
                foreach ($requirements as $pid => $needed) {
                    $stockProduct = $products->get($pid);
                    $available = isset($stockLevels[$pid])
                        ? (float) $stockLevels[$pid]->quantity
                        : (float) ($stockProduct->quantity ?? 0);

                    if ($available < $needed) {
                        return response()->json([
                            'error' => 'المنتج "' . ($stockProduct->name ?? "#$pid") . '" غير متوفر بالكمية المطلوبة'
                                     . ' (المطلوب شاملاً البونص/المكونات: ' . number_format($needed, 2)
                                     . ' / المتاح في المخزن: ' . number_format($available, 2) . ')',
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
            $changeAmount = $isCredit ? 0 : max(0, $paidAmount - ($totalAmount - $balanceUsed - $chequeAmount));

            // Validate cash + cheque + balance_used >= total for cash sales
            if (!$isCredit && ($paidAmount + $balanceUsed + $chequeAmount) < $totalAmount - 0.005) {
                return response()->json(['error' => 'المبلغ المدفوع (نقدي + شيكات + رصيد) أقل من الإجمالي المستحق'], 422);
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

            // Attach to the cashier's active shift (if one is open)
            $activeShift = CashShift::activeForUser(auth()->id());

            // Resolve branch from: active shift → warehouse → user → system default
            $saleBranchId = $activeShift?->branch_id
                ?? \App\Models\Warehouse::where('id', $warehouseId)->value('branch_id')
                ?? auth()->user()?->branch_id
                ?? \App\Models\Setting::get('default_branch_id');

            $basePaymentMethod = $isCredit ? 'cash' : $request->payment_method;

            $sale = Sale::create([
                'user_id'        => auth()->id(),
                'customer_id'    => ($isCredit || $balanceUsed > 0) ? $request->customer_id : null,
                'is_credit'      => $isCredit,
                'warehouse_id'   => $warehouseId,
                'branch_id'      => $saleBranchId,
                'cash_shift_id'  => $activeShift?->id,
                'subtotal'         => $subtotal,
                'discount'         => $discount,
                'discount_percent' => $discountPercent,
                'tax'              => $tax,
                'tax_inclusive'    => $vatInclusive,
                'total_amount'     => $totalAmount,
                'payment_method'   => $basePaymentMethod, // keeps original tender for GL
                'paid_amount'      => $paidAmount,
                'cash_amount'      => $isCredit ? 0 : round(max(0, $totalAmount - $balanceUsed - $chequeAmount), 2),
                'cheque_amount'    => $chequeAmount,
                'balance_used'     => $balanceUsed,
                'change_amount'    => $changeAmount,
                'setoff_ref'       => $request->input('setoff_ref'),
            ]);

            foreach ($normalized as $line) {
                $product = $line['product'];

                // تكلفة الوحدة: للتجميعي = مجموع تكلفة مكوناته، للخدمة = 0 (إلا إذا حُددت تكلفة)
                $costPrice = $product->isBundle()
                    ? $product->componentsCost()
                    : (float) $product->cost_price;

                SaleItem::create([
                    'sale_id'         => $sale->id,
                    'product_id'      => $product->id,
                    'quantity'        => $line['baseQty'],           // دائماً بالوحدة الرئيسية
                    'unit_price'      => $line['basePrice'],         // سعر الوحدة الرئيسية
                    'cost_price'      => $costPrice,
                    'total_price'     => $line['lineTotal'],
                    'vat_rate'        => $line['vatRate'],
                    'vat_amount'      => $line['vatAmount'],
                    'bonus_qty'       => $line['bonusQty'],
                    'product_unit_id' => $line['unit']?->id,
                    'unit_factor'     => $line['factor'],
                    'unit_label'      => $line['unit']?->name,
                ]);
                // SaleItem::created() boot handles stock movements (incl. bundle components & bonus).
                // Update per-warehouse stock_level here:
                if ($product->isBundle()) {
                    foreach ($product->components as $comp) {
                        WarehouseService::out($warehouseId, $comp->component_id,
                            round($line['baseQty'] * (float) $comp->quantity, 4));
                    }
                } elseif (!$product->isService()) {
                    WarehouseService::out($warehouseId, $product->id,
                        round($line['baseQty'] + $line['bonusQty'], 4));
                }
            }

            // ── سجل الشيك في حافظة الشيكات وربطه بالفاتورة (الدفع المختلط) ──
            if ($chequeAmount > 0) {
                $cheque = \App\Models\Check::create([
                    'type'        => 'receivable',
                    'check_ref'   => $request->input('cheque_ref'),
                    'check_date'  => now()->toDateString(),
                    'due_date'    => $request->input('cheque_due_date') ?: now()->toDateString(),
                    'amount'      => $chequeAmount,
                    'bank_name'   => $request->input('cheque_bank'),
                    'customer_id' => $request->customer_id,
                    'party_name'  => $sale->customer?->name ?? 'عميل نقدي',
                    'status'      => 'received',
                    'branch_id'   => $saleBranchId,
                    'user_id'     => auth()->id(),
                ]);
                $sale->update(['cheque_id' => $cheque->id]);
            }

            (new LedgerPostingService())->postSale($sale->load('items', 'customer'));

            DB::commit();

            $sale->load('items.product', 'customer', 'user');
            $payLabel = $isCredit ? 'آجل' : ($sale->isMixed() ? 'دفع مختلط' : match ($sale->payment_method) {
                'cash'            => 'نقدي',
                'card'            => 'بطاقة بنكية',
                'mobile_wallet'   => 'محفظة إلكترونية',
                'deposit_balance' => 'رصيد إيداع',
                default           => $sale->payment_method,
            });

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
                    'items'          => $sale->items->map(function ($i) {
                        $factor = (float) ($i->unit_factor ?: 1);
                        $isUnit = $i->product_unit_id && $factor > 0;
                        return [
                            'name'  => $i->product->name . ($isUnit ? ' — ' . $i->unit_label : ''),
                            // العرض بالوحدة المختارة (كرتون...) بينما التخزين بالوحدة الرئيسية
                            'qty'   => $isUnit ? round($i->quantity / $factor, 3) : $i->quantity,
                            'unit'  => $i->unit_label ?: ($i->product->unit ?? 'قطعة'),
                            'price' => number_format($isUnit ? $i->unit_price * $factor : $i->unit_price, 2),
                            'total' => number_format($i->total_price, 2),
                            'bonus' => (float) ($i->bonus_qty ?? 0) > 0
                                ? rtrim(rtrim(number_format($i->bonus_qty, 3), '0'), '.')
                                : null,
                        ];
                    })->toArray(),
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
