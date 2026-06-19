<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:products.view')->only(['index', 'show', 'getByBarcode']);
        $this->middleware('can:products.create')->only(['create', 'store', 'importTemplate', 'import']);
        $this->middleware('can:products.edit')->only(['edit', 'update']);
        $this->middleware('can:products.delete')->only(['destroy']);
        $this->middleware('can:inventory.view')->only(['lowStock', 'expiring', 'reorder']);
    }

    public function index(Request $request)
    {
        $class = $request->input('class'); // null=الكل | items=أصناف | manufactured=منتجات مصنّعة

        if ($request->ajax()) {
            $query = Product::with('category')->select('products.*')
                ->when($class === 'items', fn($q) => $q->whereIn('product_class', [Product::CLASS_MERCHANDISE, Product::CLASS_RAW]))
                ->when($class === 'manufactured', fn($q) => $q->where('product_class', Product::CLASS_MANUFACTURED));

            return DataTables::eloquent($query)
                ->addColumn('category_name', fn($p) => e($p->category?->name ?? '—'))
                ->addColumn('image_html', fn($p) => $p->image
                    ? '<img src="'.asset('storage/'.$p->image).'" width="40" height="40" class="rounded object-fit-cover">'
                    : '<span class="text-muted"><i class="bi bi-box-seam"></i></span>')
                ->addColumn('stock_badge', function ($p) {
                    $color = $p->isLowStock() ? 'danger' : 'success';
                    return "<span class='badge bg-{$color}'>{$p->quantity}</span>";
                })
                ->addColumn('expiry_badge', function ($p) {
                    if (!$p->expiry_date) return '<span class="text-muted">—</span>';
                    if ($p->isExpired())      return "<span class='badge bg-danger'>منتهي</span>";
                    if ($p->isExpiringSoon()) return "<span class='badge bg-warning'>{$p->expiry_date->format('Y-m-d')}</span>";
                    return "<span class='badge bg-success'>{$p->expiry_date->format('Y-m-d')}</span>";
                })
                ->addColumn('cost_fmt',    fn($p) => number_format($p->cost_price, 2))
                ->addColumn('price_fmt',   fn($p) => number_format($p->selling_price, 2))
                ->addColumn('action',      fn($p) => $this->actionButtons($p))
                ->filterColumn('category_name', fn($q, $k) =>
                    $q->whereHas('category', fn($c) => $c->where('name', 'like', "%$k%")))
                ->rawColumns(['image_html', 'stock_badge', 'expiry_badge', 'action'])
                ->setRowId('id') // يتيح النقر المزدوج على الصف لفتح بطاقة المنتج
                ->make(true);
        }

        return view('products.index', compact('class'));
    }

    public function create(Request $request)
    {
        // وضع الشاشة: صنف (بضاعة/مواد خام) أو منتج مصنّع — لفصل النماذج
        $mode        = $request->input('mode') === 'product' ? 'product' : 'item';
        $categories  = Category::orderBy('name')->get();
        $currencies  = \App\Models\Currency::where('is_active', true)->orderByDesc('is_base')->get();
        $allProducts = Product::where('product_type', Product::TYPE_GOODS)
            ->orderBy('name')->get(['id', 'name', 'cost_price']);
        return view('products.create', compact('categories', 'currencies', 'allProducts', 'mode'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        $validated = $this->prepareProductPayload($request, $validated);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::create($validated);

        $this->syncUnits($request, $product);
        $this->syncComponents($request, $product);

        return redirect()->route('products.index')->with('success', 'تم إضافة المنتج بنجاح');
    }

    /** قواعد التحقق المشتركة لإنشاء/تعديل صنف */
    private function rules(?Product $product = null): array
    {
        $unitValues = implode(',', array_column(\App\Enums\ProductUnit::cases(), 'value'));
        $barcodeRule = 'required|string|unique:products,barcode' . ($product ? ',' . $product->id : '');

        return [
            'name'             => 'required|string|max:255',
            'barcode'          => $barcodeRule,
            'category_id'      => 'required|exists:categories,id',
            'product_type'     => 'required|in:' . implode(',', array_keys(Product::$types)),
            'product_class'    => 'nullable|in:' . implode(',', array_keys(Product::$classes)),
            'description'      => 'nullable|string',
            'cost_price'       => 'required|numeric|min:0',
            'selling_price'    => 'required|numeric|min:0',
            'quantity'         => 'required|numeric|min:0',
            'min_quantity'     => 'required|numeric|min:0',
            'max_quantity'     => 'nullable|numeric|min:0',
            'reorder_level'    => 'nullable|numeric|min:0',
            'unit'             => 'required|in:' . $unitValues,
            'allow_fractions'  => 'nullable|boolean',
            'expiry_date'      => 'nullable|date|after:today',
            'image'            => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            // الضريبة لكل صنف
            'is_taxable'       => 'nullable|boolean',
            'vat_rate'         => 'nullable|numeric|min:0|max:100',
            // البونص
            'bonus_after_qty'  => 'nullable|numeric|min:0',
            'bonus_every_qty'  => 'nullable|numeric|min:0.001',
            'bonus_free_qty'   => 'nullable|numeric|min:0.001',
            // العملة الأجنبية
            'currency_id'      => 'nullable|exists:currencies,id',
            'cost_price_fc'    => 'nullable|numeric|min:0',
            'selling_price_fc' => 'nullable|numeric|min:0',
            // الوحدات الإضافية
            'units'                  => 'nullable|array|max:5',
            'units.*.id'             => 'nullable|integer',
            'units.*.name'           => 'required_with:units.*.factor|string|max:50',
            'units.*.factor'         => 'required_with:units.*.name|numeric|min:0.0001',
            'units.*.barcode'        => 'nullable|string|max:64',
            'units.*.selling_price'  => 'nullable|numeric|min:0',
            'units.*.cost_price'     => 'nullable|numeric|min:0',
            // مكونات التجميعي/التصنيع
            'components'             => 'nullable|array',
            'components.*.component_id' => 'nullable|exists:products,id',
            'components.*.quantity'     => 'nullable|numeric|min:0.0001',
        ];
    }

    /** تجهيز الحقول المحسوبة (ضريبة/بونص/عملة) قبل الحفظ */
    private function prepareProductPayload(Request $request, array $validated): array
    {
        $validated['allow_fractions'] = $request->boolean('allow_fractions');
        $validated['is_taxable']      = $request->boolean('is_taxable', true);

        // الخدمة والتجميعي: لا مخزون يُتتبع
        if (in_array($validated['product_type'], [Product::TYPE_SERVICE, Product::TYPE_BUNDLE], true)) {
            $validated['quantity'] = 0;
        }

        // أسعار بعملة أجنبية: تُحوَّل للعملة الأساسية تلقائياً وتبقى الأساسية هي المعتمدة محاسبياً
        if (!empty($validated['currency_id'])) {
            $currency = \App\Models\Currency::find($validated['currency_id']);
            if ($currency && !$currency->is_base) {
                if (!empty($validated['cost_price_fc'])) {
                    $validated['cost_price'] = round($currency->toBase((float) $validated['cost_price_fc']), 2);
                }
                if (!empty($validated['selling_price_fc'])) {
                    $validated['selling_price'] = round($currency->toBase((float) $validated['selling_price_fc']), 2);
                }
            } else {
                // عملة أساسية → لا حاجة لحقول الـ FC
                $validated['cost_price_fc'] = null;
                $validated['selling_price_fc'] = null;
            }
        } else {
            $validated['cost_price_fc'] = null;
            $validated['selling_price_fc'] = null;
        }

        unset($validated['units'], $validated['components']);

        return $validated;
    }

    /** حفظ الوحدات الإضافية للصنف */
    private function syncUnits(Request $request, Product $product): void
    {
        $rows = collect($request->input('units', []))
            ->filter(fn($u) => !empty($u['name']) && !empty($u['factor']));

        $keepIds = [];
        foreach ($rows as $row) {
            $sell = $row['selling_price'] ?? null;
            $cost = $row['cost_price'] ?? null;
            $payload = [
                'name'          => $row['name'],
                'factor'        => (float) $row['factor'],
                'barcode'       => ($row['barcode'] ?? null) ?: null,
                'selling_price' => ($sell !== null && $sell !== '') ? (float) $sell : null,
                'cost_price'    => ($cost !== null && $cost !== '') ? (float) $cost : null,
                'is_active'     => true,
            ];

            if (!empty($row['id'])) {
                $unit = $product->units()->find($row['id']);
                if ($unit) {
                    $unit->update($payload);
                    $keepIds[] = $unit->id;
                    continue;
                }
            }
            $keepIds[] = $product->units()->create($payload)->id;
        }

        // حذف الوحدات المزالة من النموذج (إلا المستخدمة في مستندات)
        $product->units()->whereNotIn('id', $keepIds)->get()->each(function ($unit) {
            $used = \App\Models\SaleItem::where('product_unit_id', $unit->id)->exists()
                 || \App\Models\PurchaseItem::where('product_unit_id', $unit->id)->exists();
            if ($used) {
                $unit->update(['is_active' => false]);
            } else {
                $unit->delete();
            }
        });
    }

    /** حفظ مكونات الصنف التجميعي/المصنّع */
    private function syncComponents(Request $request, Product $product): void
    {
        $rows = collect($request->input('components', []))
            ->filter(fn($c) => !empty($c['component_id']) && !empty($c['quantity']))
            ->reject(fn($c) => (int) $c['component_id'] === $product->id); // لا يدخل الصنف في تكوين نفسه

        $product->components()->delete();
        foreach ($rows as $row) {
            $product->components()->create([
                'component_id' => (int) $row['component_id'],
                'quantity'     => (float) $row['quantity'],
            ]);
        }
    }

    public function show(Product $product)
    {
        $product->load('category', 'purchaseItems.purchase.supplier', 'saleItems.sale.customer',
                       'units', 'components.component', 'currency');
        $costHistory = $product->costHistory()->with('changedBy:id,name')->limit(30)->get();
        $currency    = \App\Models\Setting::get('currency_symbol', 'ج.م');

        // ── رسم بياني لحركات الصنف: وارد/صادر شهرياً لآخر 12 شهر ────────────
        $from = now()->subMonths(11)->startOfMonth();
        $movementStats = \App\Models\StockMovement::selectRaw(
                "DATE_FORMAT(created_at, '%Y-%m') as ym,
                 SUM(CASE WHEN movement_type = 'in'  THEN quantity ELSE 0 END) as qty_in,
                 SUM(CASE WHEN movement_type = 'out' THEN quantity ELSE 0 END) as qty_out"
            )
            ->where('product_id', $product->id)
            ->where('created_at', '>=', $from)
            ->groupBy('ym')->orderBy('ym')
            ->get()->keyBy('ym');

        $chartLabels = [];
        $chartIn     = [];
        $chartOut    = [];
        for ($i = 11; $i >= 0; $i--) {
            $ym = now()->subMonths($i)->format('Y-m');
            $chartLabels[] = $ym;
            $chartIn[]     = (float) ($movementStats[$ym]->qty_in  ?? 0);
            $chartOut[]    = (float) ($movementStats[$ym]->qty_out ?? 0);
        }

        // إحصاءات بطاقة الصنف (كما في الأصيل: عدد الحركات وتاريخ آخر حركة)
        $movementsCount   = \App\Models\StockMovement::where('product_id', $product->id)->count();
        $lastMovementDate = \App\Models\StockMovement::where('product_id', $product->id)->latest()->value('created_at');

        // ── العلاقات المنظّمة (عرض 360°) ──────────────────────────────────────
        // الرصيد لكل مستودع
        $stockByWarehouse = \Illuminate\Support\Facades\DB::table('stock_levels as sl')
            ->join('warehouses as w', 'w.id', '=', 'sl.warehouse_id')
            ->where('sl.product_id', $product->id)
            ->where('sl.quantity', '<>', 0)
            ->select('w.name as warehouse', 'sl.quantity')
            ->orderBy('w.name')->get();

        // أحدث 100 حركة مخزون (وارد/صادر) مع المرجع
        $recentMovements = \App\Models\StockMovement::where('product_id', $product->id)
            ->latest()->limit(100)->get();

        return view('products.show', compact(
            'product', 'costHistory', 'currency',
            'chartLabels', 'chartIn', 'chartOut',
            'movementsCount', 'lastMovementDate',
            'stockByWarehouse', 'recentMovements'
        ));
    }

    public function edit(Product $product)
    {
        $categories  = Category::orderBy('name')->get();
        $currencies  = \App\Models\Currency::where('is_active', true)->orderByDesc('is_base')->get();
        $allProducts = Product::where('product_type', Product::TYPE_GOODS)
            ->where('id', '!=', $product->id)
            ->orderBy('name')->get(['id', 'name', 'cost_price']);
        $product->load('units', 'components.component');
        return view('products.edit', compact('product', 'categories', 'currencies', 'allProducts'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate($this->rules($product));

        $validated = $this->prepareProductPayload($request, $validated);

        // الكمية لا تُعدَّل من هنا للأصناف المخزنية (تُدار بالحركات) — نحافظ على القيمة الحالية
        if ($product->tracksStock()) {
            unset($validated['quantity']);
        }

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($validated);

        $this->syncUnits($request, $product);
        $this->syncComponents($request, $product);

        return redirect()->route('products.index')->with('success', 'تم تحديث المنتج بنجاح');
    }

    public function destroy(Product $product)
    {
        if ($product->saleItems()->exists() || $product->purchaseItems()->exists()) {
            return back()->with('error', 'لا يمكن حذف المنتج لأنه مرتبط بعمليات بيع أو شراء');
        }

        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return redirect()->route('products.index')->with('success', 'تم حذف المنتج بنجاح');
    }

    public function getByBarcode(string $barcode)
    {
        $product = Product::with('category')->where('barcode', $barcode)->first();
        $unit    = null;

        // باركود وحدة إضافية (كرتون/دستة...)؟
        if (!$product) {
            $unit = \App\Models\ProductUnit::with('product.category')
                ->where('barcode', $barcode)
                ->where('is_active', true)
                ->first();
            $product = $unit?->product;
        }

        if (!$product) {
            return response()->json(['error' => 'المنتج غير موجود'], 404);
        }

        // الخدمات والتجميعي لا يخضعان لفحص المخزون هنا
        if ($product->tracksStock() && $product->quantity <= 0) {
            return response()->json(['error' => 'المنتج غير متوفر في المخزون'], 400);
        }

        $payload = $product->toArray();
        if ($unit) {
            $payload['scanned_unit'] = [
                'id'            => $unit->id,
                'name'          => $unit->name,
                'factor'        => (float) $unit->factor,
                'selling_price' => $unit->effectiveSellingPrice(),
            ];
        }

        return response()->json($payload);
    }

    public function lowStock(Request $request)
    {
        // International standard (SAP/Oracle): Low-stock alerts are per-warehouse,
        // NOT based on global total. A product may be critical in WH-FLOOR (2 units)
        // even if WH-MAIN has 100 units.
        //
        // We use stock_levels.quantity <= stock_levels.min_quantity per warehouse.
        // Fallback: if no stock_level record → fall back to products global check.

        $branchId = $this->effectiveBranchId($request);

        // Get all stock_levels that are at or below their minimum
        $lowLevels = \App\Models\StockLevel::with([
                'product.category',
                'warehouse.branch',
            ])
            ->whereColumn('quantity', '<=', 'min_quantity')
            ->when($branchId, function ($q) use ($branchId) {
                $q->whereHas('warehouse', fn($wq) => $wq->where('branch_id', $branchId));
            })
            ->orderBy('quantity')
            ->paginate(25);

        $branches     = \App\Models\Branch::where('is_active', true)->orderBy('name')->get(['id','name','code']);
        $branchLocked = $this->isBranchLocked();

        return view('products.low-stock', compact('lowLevels', 'branches', 'branchId', 'branchLocked'));
    }

    /**
     * تقرير حد إعادة الطلب: الأصناف التي بلغ رصيدها حد إعادة الطلب
     * (مستوحى من "حد إعادة الطلب" في الأصيل) + الأصناف المتجاوزة للحد الأقصى.
     */
    public function reorder()
    {
        $reorderProducts = Product::with('category')
            ->where('product_type', Product::TYPE_GOODS)
            ->whereNotNull('reorder_level')
            ->whereColumn('quantity', '<=', 'reorder_level')
            ->orderBy('quantity')
            ->get();

        $overMaxProducts = Product::with('category')
            ->where('product_type', Product::TYPE_GOODS)
            ->whereNotNull('max_quantity')
            ->where('max_quantity', '>', 0)
            ->whereColumn('quantity', '>', 'max_quantity')
            ->orderByDesc('quantity')
            ->get();

        $currency = \App\Models\Setting::get('currency_symbol', 'ج.م');

        return view('products.reorder', compact('reorderProducts', 'overMaxProducts', 'currency'));
    }

    public function expiring()
    {
        $products = Product::with('category')
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now(), now()->addDays(30)])
            ->orderBy('expiry_date')
            ->paginate(20);
        return view('products.expiring', compact('products'));
    }

    /**
     * Lot/Batch expiry alerts: shows all stock movements with expiry_date
     * within the next 60 days, grouped by product + lot + warehouse.
     */
    public function lotExpiry(Request $request)
    {
        $days     = (int) $request->input('days', 60);
        $currency = \App\Models\Setting::get('currency_symbol', 'ج.م');

        $lots = \App\Models\StockMovement::with('product.category', 'warehouse')
            ->whereNotNull('expiry_date')
            ->whereNotNull('lot_number')
            ->where('movement_type', 'in')
            ->where('is_reversal', false)
            ->whereDate('expiry_date', '<=', now()->addDays($days))
            ->whereDate('expiry_date', '>=', now())
            ->orderBy('expiry_date')
            ->paginate(30)
            ->withQueryString();

        return view('products.lot-expiry', compact('lots', 'days', 'currency'));
    }

    // ── Import ───────────────────────────────────────────────────────────────

    public function importTemplate(): StreamedResponse
    {
        $categories = Category::orderBy('name')->get(['id', 'name']);

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="products-template.csv"',
        ];

        return response()->stream(function () use ($categories) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM so Excel opens the file correctly
            fwrite($out, "\xEF\xBB\xBF");

            // Headers row
            fputcsv($out, [
                'name',         // الاسم
                'barcode',      // الباركود
                'category_id',  // رقم الفئة
                'cost_price',   // سعر الشراء
                'selling_price',// سعر البيع
                'quantity',     // الكمية
                'min_quantity', // الحد الأدنى
                'unit',         // piece | kg | liter
                'expiry_date',  // YYYY-MM-DD (اختياري)
            ]);

            // Example row
            fputcsv($out, [
                'أرز مصري',
                '6221234567890',
                $categories->first()?->id ?? 1,
                '10.50',
                '15.00',
                '100',
                '10',
                'kg',
                '',
            ]);

            // Reference sheet: category IDs
            fputcsv($out, []);
            fputcsv($out, ['--- الفئات المتاحة ---']);
            fputcsv($out, ['رقم الفئة (category_id)', 'اسم الفئة']);
            foreach ($categories as $cat) {
                fputcsv($out, [$cat->id, $cat->name]);
            }

            fputcsv($out, []);
            fputcsv($out, ['--- الوحدات المتاحة (unit) ---']);
            fputcsv($out, ['piece = قطعة', 'kg = كيلو', 'liter = لتر']);

            fclose($out);
        }, 200, $headers);
    }

    public function import(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $path    = $request->file('csv_file')->getRealPath();
        $handle  = fopen($path, 'r');

        // Strip BOM if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header  = fgetcsv($handle); // skip header row
        $created = 0;
        $updated = 0;
        $errors  = [];
        $row     = 1;

        $validUnits     = ['piece', 'kg', 'liter'];
        $categoryIds    = Category::pluck('id')->toArray();

        while (($data = fgetcsv($handle)) !== false) {
            $row++;

            // Skip blank/comment rows
            if (empty(array_filter($data)) || str_starts_with($data[0] ?? '', '---')) {
                continue;
            }

            [$name, $barcode, $categoryId, $costPrice, $sellingPrice, $quantity, $minQty, $unit, $expiryDate]
                = array_pad($data, 9, null);

            // Validate row
            $rowErrors = [];
            if (empty($name))      $rowErrors[] = 'الاسم مطلوب';
            if (empty($barcode))   $rowErrors[] = 'الباركود مطلوب';
            if (!in_array((int)$categoryId, $categoryIds)) $rowErrors[] = 'رقم الفئة غير صحيح';
            if (!is_numeric($costPrice))    $rowErrors[] = 'سعر الشراء غير صحيح';
            if (!is_numeric($sellingPrice)) $rowErrors[] = 'سعر البيع غير صحيح';
            if (!in_array($unit, $validUnits)) $rowErrors[] = 'الوحدة يجب أن تكون: piece أو kg أو liter';

            if ($rowErrors) {
                $errors[] = "سطر {$row}: " . implode(', ', $rowErrors);
                continue;
            }

            $payload = [
                'name'          => trim($name),
                'category_id'   => (int) $categoryId,
                'cost_price'    => (float) $costPrice,
                'selling_price' => (float) $sellingPrice,
                'quantity'      => max(0, (int) $quantity),
                'min_quantity'  => max(0, (int) $minQty),
                'unit'          => $unit,
                'expiry_date'   => (!empty($expiryDate) && strtotime($expiryDate)) ? $expiryDate : null,
            ];

            $existing = Product::where('barcode', trim($barcode))->first();
            if ($existing) {
                $existing->update($payload);
                $updated++;
            } else {
                Product::create(array_merge($payload, ['barcode' => trim($barcode)]));
                $created++;
            }
        }

        fclose($handle);

        $msg = "تم الاستيراد: {$created} منتج جديد، {$updated} محدَّث.";
        if ($errors) {
            $msg .= ' أخطاء: ' . implode(' | ', $errors);
            return redirect()->route('products.index')->with('warning', $msg);
        }

        return redirect()->route('products.index')->with('success', $msg);
    }

    // ── private helpers ──────────────────────────────────────────────────────

    private function actionButtons(Product $p): string
    {
        $user  = auth()->user();
        $show  = '<a href="'.route('products.show', $p).'" class="btn btn-sm btn-info btn-action" title="عرض"><i class="bi bi-eye"></i></a>';
        $edit  = $user->can('products.edit')
            ? '<a href="'.route('products.edit', $p).'" class="btn btn-sm btn-primary btn-action" title="تعديل"><i class="bi bi-pencil"></i></a>'
            : '';
        $token = csrf_token();
        $del   = $user->can('products.delete')
            ? '<form action="'.route('products.destroy', $p).'" method="POST" class="d-inline"'
              . ' onsubmit="return confirm(\'هل أنت متأكد؟\')">'
              . '<input type="hidden" name="_token" value="'.$token.'">'
              . '<input type="hidden" name="_method" value="DELETE">'
              . '<button class="btn btn-sm btn-danger btn-action" title="حذف"><i class="bi bi-trash"></i></button></form>'
            : '';
        return '<div class="d-flex gap-1 flex-nowrap">'.$show.$edit.$del.'</div>';
    }
}
