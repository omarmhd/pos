<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Services\LedgerPostingService;
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

        return response()->json($customers);
    }

    public function store(Request $request)
    {
        $request->validate([
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'subtotal'           => 'required|numeric|min:0',
            'discount'           => 'nullable|numeric|min:0',
            'tax'                => 'nullable|numeric|min:0',
            'total_amount'       => 'required|numeric|min:0',
            'is_credit'          => 'nullable|boolean',
            'customer_id'        => 'required_if:is_credit,true|nullable|exists:customers,id',
            'payment_method'     => 'required_unless:is_credit,true|nullable|in:cash,card,mobile_wallet',
            'paid_amount'        => 'required|numeric|min:0',
        ]);

        // ── Load settings (authoritative for calculation) ──────────────────
        $vatEnabled    = (bool) Setting::get('vat_enabled',           0);
        $vatRate       = (float) Setting::get('vat_rate',             15);
        $vatInclusive  = (bool) Setting::get('vat_inclusive',         0);
        $allowNegStock = (bool) Setting::get('allow_negative_stock',  0);
        $maxDiscPct    = (float) Setting::get('max_discount_percent', 100);

        $isCredit  = (bool) $request->input('is_credit', false);
        $subtotal  = round((float) $request->subtotal, 2);
        $discount  = round((float) ($request->discount ?? 0), 2);

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
            $productIds = collect($request->items)->pluck('product_id')->unique()->values()->all();
            $products   = Product::whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');

            foreach ($request->items as $item) {
                $product = $products->get($item['product_id']);
                if (!$product || (!$allowNegStock && $product->quantity < $item['quantity'])) {
                    return response()->json([
                        'error' => 'المنتج "' . ($product?->name ?? '#' . $item['product_id']) . '" غير متوفر بالكمية المطلوبة',
                    ], 400);
                }
            }

            $paidAmount   = $isCredit ? 0 : (float) $request->paid_amount;
            $changeAmount = $isCredit ? 0 : max(0, $paidAmount - $totalAmount);

            // Validate paid >= total for cash sales
            if (!$isCredit && $paidAmount < $totalAmount - 0.005) {
                return response()->json(['error' => 'المبلغ المدفوع أقل من الإجمالي المستحق'], 422);
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
                'customer_id'    => $isCredit ? $request->customer_id : null,
                'is_credit'      => $isCredit,
                'subtotal'       => $subtotal,
                'discount'       => $discount,
                'tax'            => $tax,
                'total_amount'   => $totalAmount,
                'payment_method' => $isCredit ? 'cash' : $request->payment_method,
                'paid_amount'    => $paidAmount,
                'change_amount'  => $changeAmount,
            ]);

            foreach ($request->items as $item) {
                $product = $products->get($item['product_id']);
                SaleItem::create([
                    'sale_id'     => $sale->id,
                    'product_id'  => $item['product_id'],
                    'quantity'    => $item['quantity'],
                    'unit_price'  => $item['unit_price'],
                    'cost_price'  => $product->cost_price,
                    'total_price' => $item['quantity'] * $item['unit_price'],
                ]);
                $product->decrement('quantity', $item['quantity']);
            }

            (new LedgerPostingService())->postSale($sale->load('items', 'customer'));

            DB::commit();

            $sale->load('items.product', 'customer', 'user');
            $payLabel = $isCredit ? 'آجل' : match ($sale->payment_method) {
                'cash'          => 'نقدي',
                'card'          => 'بطاقة بنكية',
                'mobile_wallet' => 'محفظة إلكترونية',
                default         => $sale->payment_method,
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
                        'price' => number_format($i->unit_price, 2),
                        'total' => number_format($i->total_price, 2),
                    ])->toArray(),
                    'subtotal'    => number_format($sale->subtotal, 2),
                    'discount'    => number_format($sale->discount, 2),
                    'has_discount'=> $sale->discount > 0,
                    'tax'         => number_format($sale->tax, 2),
                    'has_tax'     => $sale->tax > 0,
                    'tax_rate'    => $vatRate,
                    'total'       => number_format($sale->total_amount, 2),
                    'paid'        => number_format($paidAmount, 2),
                    'change'      => number_format($changeAmount, 2),
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
