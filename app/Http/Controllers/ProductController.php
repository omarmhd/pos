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
        $this->middleware('can:inventory.view')->only(['lowStock', 'expiring']);
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Product::with('category')->select('products.*');

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
                ->make(true);
        }

        return view('products.index');
    }

    public function create()
    {
        $categories = Category::orderBy('name')->get();
        return view('products.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'barcode'       => 'required|string|unique:products,barcode',
            'category_id'   => 'required|exists:categories,id',
            'description'   => 'nullable|string',
            'cost_price'    => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'quantity'      => 'required|numeric|min:0',
            'min_quantity'  => 'required|numeric|min:0',
            'unit'          => 'required|in:piece,kg,g,liter,ml',
            'allow_fractions' => 'nullable|boolean',
            'expiry_date'   => 'nullable|date|after:today',
            'image'         => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $validated['allow_fractions'] = $request->boolean('allow_fractions');

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        Product::create($validated);

        return redirect()->route('products.index')->with('success', 'تم إضافة المنتج بنجاح');
    }

    public function show(Product $product)
    {
        $product->load('category', 'purchaseItems.purchase', 'saleItems.sale');
        return view('products.show', compact('product'));
    }

    public function edit(Product $product)
    {
        $categories = Category::orderBy('name')->get();
        return view('products.edit', compact('product', 'categories'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'barcode'       => 'required|string|unique:products,barcode,'.$product->id,
            'category_id'   => 'required|exists:categories,id',
            'description'   => 'nullable|string',
            'cost_price'    => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'quantity'      => 'required|numeric|min:0',
            'min_quantity'  => 'required|numeric|min:0',
            'unit'          => 'required|in:piece,kg,g,liter,ml',
            'allow_fractions' => 'nullable|boolean',
            'expiry_date'   => 'nullable|date|after:today',
            'image'         => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $validated['allow_fractions'] = $request->boolean('allow_fractions');

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($validated);

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

        if (!$product) {
            return response()->json(['error' => 'المنتج غير موجود'], 404);
        }

        if ($product->quantity <= 0) {
            return response()->json(['error' => 'المنتج غير متوفر في المخزون'], 400);
        }

        return response()->json($product);
    }

    public function lowStock()
    {
        $products = Product::with('category')->whereRaw('quantity <= min_quantity')->paginate(20);
        return view('products.low-stock', compact('products'));
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
