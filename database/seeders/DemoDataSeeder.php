<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Shift;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    private int $adminId;
    private array $acc = []; // account_code => account_id

    public function run(): void
    {
        $this->adminId = User::where('email', 'admin@demo.com')->value('id');
        Account::all()->each(fn($a) => $this->acc[$a->code] = $a->id);

        $categories = $this->seedCategories();
        $suppliers  = $this->seedSuppliers();
        $products   = $this->seedProducts($categories);
        $customers  = $this->seedCustomers();

        $this->seedPurchases($suppliers, $products);
        $this->seedSales($customers, $products);
        $this->recalcQuantities($products);
        $this->seedHR();
    }

    // ── Categories ────────────────────────────────────────────────────────
    private function seedCategories(): array
    {
        return collect([
            ['name' => 'مواد غذائية جافة',    'description' => 'أرز وسكر ودقيق وزيوت وبقوليات'],
            ['name' => 'مشروبات',             'description' => 'مياه وعصائر ومشروبات غازية وطاقة'],
            ['name' => 'منتجات الألبان',       'description' => 'حليب وجبن وزبادي وزبدة'],
            ['name' => 'خضروات وفواكه',        'description' => 'منتجات طازجة موسمية'],
            ['name' => 'لحوم ودواجن',          'description' => 'دجاج ولحم بقري وأسماك'],
            ['name' => 'معجنات وخبز',          'description' => 'خبز وكعك ومعجنات يومية'],
            ['name' => 'منظفات ومواد تنظيف',  'description' => 'مساحيق غسيل وسوائل تنظيف ومعقمات'],
            ['name' => 'عناية شخصية',         'description' => 'شامبو وصابون ومستحضرات'],
            ['name' => 'وجبات خفيفة وحلويات', 'description' => 'رقائق وشوكولاتة وحلوى'],
            ['name' => 'مجمدات',              'description' => 'خضروات مجمدة ووجبات جاهزة'],
        ])->map(fn($r) => Category::create($r))->all();
    }

    // ── Suppliers ─────────────────────────────────────────────────────────
    private function seedSuppliers(): array
    {
        return collect([
            ['name' => 'شركة النور للتوزيع',          'phone' => '0501234567', 'email' => 'alnoor@dist.sa',     'company' => 'النور للتوزيع',         'address' => 'الرياض - طريق الملك عبدالله'],
            ['name' => 'مؤسسة الخير للمواد الغذائية', 'phone' => '0507654321', 'email' => 'alkhayr@food.sa',    'company' => 'الخير للمواد الغذائية', 'address' => 'جدة - المنطقة الصناعية'],
            ['name' => 'مصنع الحليب الذهبي',          'phone' => '0509876543', 'email' => 'goldmilk@dairy.sa',  'company' => 'الحليب الذهبي',         'address' => 'الدمام - المنطقة الصناعية'],
            ['name' => 'مزارع الطازج للتوزيع',        'phone' => '0551112233', 'email' => 'fresh@farms.sa',     'company' => 'مزارع الطازج',          'address' => 'القصيم - حائل'],
            ['name' => 'شركة النظافة والتنظيف',       'phone' => '0554445566', 'email' => 'clean@supply.sa',    'company' => 'النظافة والتنظيف',      'address' => 'الرياض - حي الملز'],
            ['name' => 'توزيع المشروبات الوطنية',     'phone' => '0557778899', 'email' => 'drinks@national.sa', 'company' => 'المشروبات الوطنية',     'address' => 'جدة - ميناء جدة الإسلامي'],
        ])->map(fn($r) => Supplier::create($r))->all();
    }

    // ── Products ──────────────────────────────────────────────────────────
    private function seedProducts(array $categories): array
    {
        $inv  = $this->acc['1300'] ?? null;
        $cogs = $this->acc['5000'] ?? null;

        // [cat_idx, name, barcode, cost, sell, unit, min_qty]
        $rows = [
            // 0-4   مواد غذائية جافة
            [0, 'أرز بسمتي 5 كيلو',           '6281100000001', 18.00, 25.00, 'piece', 20],
            [0, 'سكر أبيض 2 كيلو',            '6281100000002',  5.50,  8.00, 'piece', 30],
            [0, 'دقيق متعدد الأغراض 5 كيلو',  '6281100000003', 12.00, 17.00, 'piece', 15],
            [0, 'زيت نباتي 1.5 لتر',          '6281100000004',  9.00, 13.50, 'piece', 25],
            [0, 'عدس أحمر 1 كيلو',            '6281100000005',  6.00,  9.00, 'piece', 20],
            // 5-8   مشروبات
            [1, 'مياه معدنية 1.5 لتر',         '6281100000006',  1.00,  1.75, 'piece', 60],
            [1, 'عصير برتقال 1 لتر',           '6281100000007',  4.50,  6.50, 'piece', 30],
            [1, 'مشروب طاقة 250 مل',           '6281100000008',  5.00,  8.00, 'piece', 20],
            [1, 'مشروب غازي كبير 350 مل',      '6281100000009',  2.00,  3.50, 'piece', 40],
            // 9-12  منتجات الألبان
            [2, 'حليب طازج 1 لتر',            '6281100000010',  4.50,  6.00, 'piece', 40],
            [2, 'زبادي طبيعي 180 جرام',        '6281100000011',  1.50,  2.50, 'piece', 50],
            [2, 'جبن أبيض 500 جرام',          '6281100000012', 11.00, 16.00, 'piece', 20],
            [2, 'زبدة 200 جرام',              '6281100000013',  7.00, 10.00, 'piece', 15],
            // 13-16 خضروات وفواكه
            [3, 'طماطم طازجة 1 كيلو',         '6281100000014',  2.00,  4.00, 'kg',   20],
            [3, 'بصل 1 كيلو',                 '6281100000015',  1.50,  3.00, 'kg',   20],
            [3, 'تفاح أحمر 1 كيلو',           '6281100000016',  5.00,  8.00, 'kg',   15],
            [3, 'موز 1 كيلو',                 '6281100000017',  3.50,  5.50, 'kg',   15],
            // 17-19 لحوم ودواجن
            [4, 'دجاج كامل مبرد 1.2 كيلو',    '6281100000018', 18.00, 26.00, 'piece', 10],
            [4, 'لحم بقري مفروم 500 جرام',     '6281100000019', 22.00, 32.00, 'piece', 10],
            [4, 'صدر دجاج 500 جرام',          '6281100000020', 14.00, 20.00, 'piece', 10],
            // 20-21 معجنات وخبز
            [5, 'خبز أبيض شريحة 600 جرام',    '6281100000021',  2.50,  4.00, 'piece', 30],
            [5, 'كرواسان شوكولاتة 6 قطع',      '6281100000022',  7.00, 11.00, 'piece', 15],
            // 22-24 منظفات
            [6, 'مسحوق غسيل 3 كيلو',          '6281100000023', 15.00, 22.00, 'piece', 15],
            [6, 'سائل جلي أواني 750 مل',       '6281100000024',  6.00,  9.00, 'piece', 20],
            [6, 'معطر جو 300 مل',             '6281100000025',  8.00, 12.00, 'piece', 15],
            // 25-26 عناية شخصية
            [7, 'شامبو للشعر 400 مل',         '6281100000026', 12.00, 18.00, 'piece', 15],
            [7, 'صابون سائل 500 مل',          '6281100000027',  7.00, 11.00, 'piece', 20],
            // 27-29 وجبات خفيفة
            [8, 'رقائق بطاطس 170 جرام',       '6281100000028',  5.00,  8.00, 'piece', 25],
            [8, 'شوكولاتة حليب 100 جرام',      '6281100000029',  4.50,  7.00, 'piece', 25],
            [8, 'بسكويت 200 جرام',            '6281100000030',  3.00,  5.00, 'piece', 30],
            // 30-31 مجمدات
            [9, 'خضروات مجمدة مشكلة 1 كيلو',  '6281100000031',  8.00, 13.00, 'piece', 15],
            [9, 'بيتزا جاهزة مجمدة متوسطة',   '6281100000032', 18.00, 27.00, 'piece', 10],
        ];

        return collect($rows)->map(function ($r) use ($categories, $inv, $cogs) {
            [$catIdx, $name, $barcode, $cost, $sell, $unit, $minQty] = $r;
            return Product::create([
                'name'                 => $name,
                'barcode'              => $barcode,
                'category_id'          => $categories[$catIdx]->id,
                'cost_price'           => $cost,
                'selling_price'        => $sell,
                'quantity'             => 0,
                'min_quantity'         => $minQty,
                'unit'                 => $unit,
                'inventory_account_id' => $inv,
                'cogs_account_id'      => $cogs,
            ]);
        })->all();
    }

    // ── Customers ─────────────────────────────────────────────────────────
    private function seedCustomers(): array
    {
        return collect([
            ['name' => 'محمد علي الأحمد',       'phone' => '0501112222', 'email' => 'mohammed@mail.com', 'credit_limit' => 1000, 'address' => 'الرياض - حي النزهة'],
            ['name' => 'فاطمة خالد المطيري',    'phone' => '0502223333', 'email' => 'fatima@mail.com',   'credit_limit' => 800,  'address' => 'الرياض - حي الروضة'],
            ['name' => 'أحمد سعود القحطاني',    'phone' => '0503334444', 'email' => null,                'credit_limit' => 1500, 'address' => 'جدة - حي الصفا'],
            ['name' => 'نورة عبدالله الحربي',   'phone' => '0504445555', 'email' => 'noura@mail.com',    'credit_limit' => 600,  'address' => 'الدمام - حي الفيصلية'],
            ['name' => 'خالد إبراهيم الزهراني', 'phone' => '0505556666', 'email' => null,                'credit_limit' => 2000, 'address' => 'مكة - حي العزيزية'],
            ['name' => 'سارة محمد السهلي',      'phone' => '0506667777', 'email' => 'sara@mail.com',     'credit_limit' => 500,  'address' => 'الرياض - حي الملقا'],
            ['name' => 'عمر يوسف البلوي',       'phone' => '0507778888', 'email' => null,                'credit_limit' => 1200, 'address' => 'جدة - حي الحمراء'],
            ['name' => 'هند سعد الدوسري',       'phone' => '0508889999', 'email' => 'hind@mail.com',     'credit_limit' => 700,  'address' => 'الرياض - حي الياسمين'],
            ['name' => 'عبدالرحمن ناصر الشمري', 'phone' => '0509990000', 'email' => null,                'credit_limit' => 3000, 'address' => 'حائل - المنطقة المركزية'],
            ['name' => 'منى حسن العتيبي',       'phone' => '0551001001', 'email' => 'mona@mail.com',     'credit_limit' => 900,  'address' => 'الطائف - حي الربوة'],
            ['name' => 'سلطان فهد الغامدي',     'phone' => '0552002002', 'email' => null,                'credit_limit' => 1800, 'address' => 'أبها - حي النسيم'],
            ['name' => 'رنا عمر الجهني',        'phone' => '0553003003', 'email' => 'rana@mail.com',     'credit_limit' => 400,  'address' => 'المدينة - حي الأنصار'],
            ['name' => 'بدر عبدالله الرشيدي',   'phone' => '0554004004', 'email' => null,                'credit_limit' => 2500, 'address' => 'الرياض - حي السلامة'],
            ['name' => 'لين حمد الأسمري',       'phone' => '0555005005', 'email' => 'leen@mail.com',     'credit_limit' => 600,  'address' => 'جدة - حي الزهراء'],
            ['name' => 'تركي وليد المالكي',     'phone' => '0556006006', 'email' => null,                'credit_limit' => 1100, 'address' => 'الرياض - حي القيروان'],
        ])->map(fn($r) => Customer::create($r))->all();
    }

    // ── Purchases ─────────────────────────────────────────────────────────
    private function seedPurchases(array $suppliers, array $products): void
    {
        // [sup_idx, days_ago, [[prod_idx, qty], ...], payment_status]
        $data = [
            [5, 88, [[5,100],[6,60],[7,50],[8,80]],                                       'paid'],
            [0, 85, [[0,80],[1,120],[2,60],[3,90],[4,70]],                                'paid'],
            [2, 83, [[9,100],[10,150],[11,60],[12,40]],                                   'paid'],
            [3, 80, [[13,50],[14,60],[15,40],[16,30],[17,30],[18,20],[20,60],[21,40]],     'paid'],
            [4, 78, [[22,40],[23,60],[24,50],[25,30],[26,40]],                            'paid'],
            [1, 75, [[0,60],[1,100],[27,80],[28,70],[29,90]],                             'paid'],
            [5, 70, [[5,120],[6,80],[7,60],[8,100]],                                      'paid'],
            [2, 67, [[9,80],[10,120],[11,50],[12,30]],                                    'paid'],
            [3, 65, [[13,60],[14,70],[15,35],[16,25],[19,25],[20,70],[21,50]],             'paid'],
            [0, 62, [[0,70],[1,80],[2,50],[3,70],[4,60]],                                 'paid'],
            [4, 60, [[22,35],[23,55],[24,45],[25,25],[26,35]],                            'paid'],
            [1, 55, [[27,90],[28,80],[29,100],[30,30],[31,20]],                           'paid'],
            [5, 50, [[5,100],[6,70],[7,55],[8,90]],                                       'paid'],
            [2, 47, [[9,90],[10,130],[11,55],[12,35]],                                    'paid'],
            [0, 45, [[0,75],[1,90],[2,55],[3,80],[4,65]],                                 'paid'],
            [3, 42, [[13,55],[14,65],[15,38],[16,28],[17,28],[18,22],[20,65],[21,45]],     'partial'],
            [1, 38, [[27,80],[28,70],[29,85],[30,25],[31,18]],                            'paid'],
            [4, 35, [[22,40],[23,60],[24,50],[25,30],[26,40]],                            'paid'],
            [5, 28, [[5,110],[6,75],[7,58],[8,85]],                                       'partial'],
            [2, 25, [[9,85],[10,125],[11,52],[12,32]],                                    'paid'],
            [0, 22, [[0,65],[1,85],[2,48],[3,72],[4,58]],                                 'paid'],
            [3, 18, [[13,50],[14,60],[15,32],[16,25],[19,22],[20,60],[21,40]],             'partial'],
            [4, 12, [[22,38],[23,58],[24,48],[25,28],[26,38]],                            'unpaid'],
            [5,  6, [[5,90],[6,65],[7,50],[8,75]],                                        'unpaid'],
        ];

        foreach ($data as $seq => [$supIdx, $daysAgo, $items, $status]) {
            $date    = Carbon::now()->subDays($daysAgo)->setHour(10)->setMinute(0)->setSecond(0);
            $dateStr = $date->toDateTimeString();
            $total   = collect($items)->sum(fn($i) => $products[$i[0]]->cost_price * $i[1]);
            $paid    = match ($status) {
                'paid'    => $total,
                'partial' => round($total * 0.5, 2),
                default   => 0.00,
            };

            $purchaseId = DB::table('purchases')->insertGetId([
                'invoice_number' => 'PUR-' . $date->format('Ymd') . '-' . str_pad($seq + 1, 4, '0', STR_PAD_LEFT),
                'supplier_id'    => $suppliers[$supIdx]->id,
                'user_id'        => $this->adminId,
                'total_amount'   => round($total, 2),
                'payment_status' => $status,
                'paid_amount'    => $paid,
                'notes'          => null,
                'is_posted'      => 0, // GL entries created by migration 100004
                'is_reversed'    => 0,
                'created_at'     => $dateStr,
                'updated_at'     => $dateStr,
            ]);

            foreach ($items as [$prodIdx, $qty]) {
                $p = $products[$prodIdx];
                DB::table('purchase_items')->insert([
                    'purchase_id' => $purchaseId,
                    'product_id'  => $p->id,
                    'quantity'    => $qty,
                    'unit_price'  => $p->cost_price,
                    'total_price' => round($p->cost_price * $qty, 2),
                    'created_at'  => $dateStr,
                    'updated_at'  => $dateStr,
                ]);
            }
        }
    }

    // ── Sales ─────────────────────────────────────────────────────────────
    private function seedSales(array $customers, array $products): void
    {
        $methods = ['cash', 'cash', 'cash', 'card', 'card', 'mobile_wallet'];
        $numC    = count($customers);

        // 48 templates: each entry = [[prod_idx, qty], ...]
        $templates = [
            [[0,2],[9,1],[5,3]],       [[27,2],[28,1],[29,1]],    [[1,3],[10,2]],
            [[13,2],[14,2],[15,1]],    [[6,2],[7,1],[8,2]],       [[22,1],[23,1],[24,1]],
            [[17,1],[20,2],[5,4]],     [[25,1],[26,1],[29,2]],    [[0,1],[3,2],[4,1]],
            [[9,3],[10,3],[11,1]],     [[5,6],[6,1]],             [[28,3],[29,2],[27,1]],
            [[2,2],[1,2],[3,1]],       [[15,2],[16,2]],           [[18,1],[19,1],[20,3]],
            [[23,2],[26,1],[5,3]],     [[7,3],[8,3]],             [[0,3],[1,1]],
            [[10,4],[12,1],[5,2]],     [[21,2],[29,1],[28,2]],    [[4,2],[3,2],[1,1]],
            [[13,3],[14,3],[16,1]],    [[6,3],[7,1],[27,2]],      [[22,2],[24,1]],
            [[17,2],[9,2]],            [[25,2],[28,3]],            [[0,2],[2,1],[4,1]],
            [[11,2],[12,1],[9,1]],     [[5,8]],                   [[29,3],[28,2],[27,2]],
            [[1,4],[10,2],[6,1]],      [[15,3],[13,2]],           [[19,2],[20,2],[5,4]],
            [[23,3],[22,1]],           [[8,4],[7,2]],             [[3,3],[4,2],[0,1]],
            [[9,2],[10,2],[5,2]],      [[21,3],[20,2]],           [[27,4],[28,3]],
            [[2,3],[3,2]],             [[16,2],[15,2],[13,1]],    [[18,1],[17,1],[9,2]],
            [[24,2],[26,2],[5,2]],     [[6,2],[7,2],[8,1]],       [[0,1],[1,2],[2,1]],
            [[10,3],[11,1],[12,1]],    [[31,1],[30,2],[5,3]],     [[29,2],[27,3]],
        ];

        $numT = count($templates);

        for ($i = 0; $i < 80; $i++) {
            $daysAgo = (int) round(88 - ($i * 88 / 79));
            $date    = Carbon::now()->subDays($daysAgo)->setHour(8 + ($i % 13))->setMinute($i % 59)->setSecond(0);
            $dateStr = $date->toDateTimeString();

            $isCredit   = ($i % 7 === 0);
            $hasCustomer = $isCredit || ($i % 3 === 0);
            $customer   = $hasCustomer ? $customers[$i % $numC] : null;
            $method     = $isCredit ? 'cash' : $methods[$i % 6];
            $hasDiscount = ($i % 10 === 0);
            $isPosted   = $daysAgo > 7;

            $template = $templates[$i % $numT];
            $subtotal = collect($template)->sum(fn($t) => $products[$t[0]]->selling_price * $t[1]);
            $discount = $hasDiscount ? round($subtotal * 0.05, 2) : 0.00;
            $total    = round($subtotal - $discount, 2);
            $paid     = $isCredit ? 0.00 : $total;

            $saleId = DB::table('sales')->insertGetId([
                'invoice_number' => 'INV-' . $date->format('Ymd') . '-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                'user_id'        => $this->adminId,
                'customer_id'    => $customer?->id,
                'is_credit'      => $isCredit ? 1 : 0,
                'subtotal'       => round($subtotal, 2),
                'discount'       => $discount,
                'tax'            => 0.00,
                'total_amount'   => $total,
                'payment_method' => $method,
                'paid_amount'    => $paid,
                'change_amount'  => 0.00,
                'is_posted'      => 0, // GL entries created by migration 100004
                'is_reversed'    => 0,
                'created_at'     => $dateStr,
                'updated_at'     => $dateStr,
            ]);

            foreach ($template as [$prodIdx, $qty]) {
                $p = $products[$prodIdx];
                DB::table('sale_items')->insert([
                    'sale_id'     => $saleId,
                    'product_id'  => $p->id,
                    'quantity'    => $qty,
                    'unit_price'  => $p->selling_price,
                    'cost_price'  => $p->cost_price,
                    'total_price' => round($p->selling_price * $qty, 2),
                    'created_at'  => $dateStr,
                    'updated_at'  => $dateStr,
                ]);
            }

            // Partial payment for every other credit sale
            if ($isCredit && $customer && ($i % 14 === 0)) {
                $payAmt  = round($total * 0.5, 2);
                $payDate = $date->copy()->addDays(3);
                DB::table('customer_payments')->insert([
                    'customer_id'    => $customer->id,
                    'sale_id'        => $saleId,
                    'amount'         => $payAmt,
                    'payment_method' => 'cash',
                    'received_at'    => $payDate->toDateString(),
                    'notes'          => 'دفعة جزئية',
                    'user_id'        => $this->adminId,
                    'created_at'     => $payDate->toDateTimeString(),
                    'updated_at'     => $payDate->toDateTimeString(),
                ]);
            }
        }
    }

    // ── Recalculate product stock from purchase/sale totals ───────────────
    private function recalcQuantities(array $products): void
    {
        foreach ($products as $product) {
            $in  = DB::table('purchase_items')->where('product_id', $product->id)->sum('quantity');
            $out = DB::table('sale_items')->where('product_id', $product->id)->sum('quantity');
            DB::table('products')->where('id', $product->id)
                ->update(['quantity' => max(0, $in - $out)]);
        }
    }

    // ── HR ────────────────────────────────────────────────────────────────
    private function seedHR(): void
    {
        $shifts    = $this->createShifts();
        $employees = $this->createEmployees();
        $this->createAttendance($employees, $shifts);
        $this->createPayrollRun($employees, 2);
        $this->createPayrollRun($employees, 1);
    }

    private function createShifts(): array
    {
        return [
            Shift::create(['name' => 'صباحي', 'start_time' => '07:00', 'end_time' => '15:00', 'hours' => 8, 'is_active' => true]),
            Shift::create(['name' => 'مسائي', 'start_time' => '15:00', 'end_time' => '23:00', 'hours' => 8, 'is_active' => true]),
            Shift::create(['name' => 'ليلي',  'start_time' => '23:00', 'end_time' => '07:00', 'hours' => 8, 'is_active' => true]),
        ];
    }

    private function createEmployees(): array
    {
        $rows = [
            ['name' => 'أحمد محمد الرشيد',    'national_id' => '1010101010', 'phone' => '0501010101', 'email' => 'ahmed@store.sa',
             'hire_date' => now()->subMonths(14)->toDateString(), 'employment_type' => 'full_time',  'pay_type' => 'monthly',
             'base_salary' => 4500, 'housing_allowance' => 800, 'transport_allowance' => 400, 'other_allowances' => 0,
             'department' => 'المبيعات', 'job_title' => 'كاشير أول'],
            ['name' => 'خالد عبدالله السليم',  'national_id' => '1020202020', 'phone' => '0502020202', 'email' => null,
             'hire_date' => now()->subMonths(8)->toDateString(),  'employment_type' => 'full_time',  'pay_type' => 'monthly',
             'base_salary' => 3800, 'housing_allowance' => 700, 'transport_allowance' => 300, 'other_allowances' => 0,
             'department' => 'المبيعات', 'job_title' => 'كاشير'],
            ['name' => 'سلمى عمر البريكي',    'national_id' => '1030303030', 'phone' => '0503030303', 'email' => 'salma@store.sa',
             'hire_date' => now()->subMonths(20)->toDateString(), 'employment_type' => 'full_time',  'pay_type' => 'monthly',
             'base_salary' => 5200, 'housing_allowance' => 900, 'transport_allowance' => 500, 'other_allowances' => 0,
             'department' => 'الإدارة', 'job_title' => 'مدير المبيعات'],
            ['name' => 'ياسر حمد العنزي',     'national_id' => '1040404040', 'phone' => '0504040404', 'email' => null,
             'hire_date' => now()->subMonths(5)->toDateString(),  'employment_type' => 'part_time',  'pay_type' => 'hourly',
             'base_salary' => 35,   'housing_allowance' => 0,   'transport_allowance' => 0,   'other_allowances' => 0,
             'department' => 'المستودع', 'job_title' => 'مخزنجي'],
            ['name' => 'ريم سعود الفيفي',     'national_id' => '1050505050', 'phone' => '0505050505', 'email' => 'reem@store.sa',
             'hire_date' => now()->subMonths(11)->toDateString(), 'employment_type' => 'full_time',  'pay_type' => 'monthly',
             'base_salary' => 4200, 'housing_allowance' => 750, 'transport_allowance' => 350, 'other_allowances' => 0,
             'department' => 'المحاسبة', 'job_title' => 'محاسبة'],
            ['name' => 'طارق نايف الحمدان',   'national_id' => '1060606060', 'phone' => '0506060606', 'email' => null,
             'hire_date' => now()->subMonths(3)->toDateString(),  'employment_type' => 'full_time',  'pay_type' => 'monthly',
             'base_salary' => 3500, 'housing_allowance' => 650, 'transport_allowance' => 300, 'other_allowances' => 0,
             'department' => 'المبيعات', 'job_title' => 'كاشير'],
        ];

        $idx = 0;
        return collect($rows)->map(function ($r) use (&$idx) {
            $r['employee_code'] = 'EMP-' . str_pad(++$idx, 4, '0', STR_PAD_LEFT);
            $r['is_active']     = true;
            return Employee::create($r);
        })->all();
    }

    private function createAttendance(array $employees, array $shifts): void
    {
        // shift assignment: emp_index => shift_index
        $shiftMap = [0 => 0, 1 => 0, 2 => 1, 3 => 0, 4 => 1, 5 => 2];

        foreach ($employees as $empIdx => $employee) {
            $shift = $shifts[$shiftMap[$empIdx]];

            for ($day = 29; $day >= 0; $day--) {
                $date = Carbon::now()->subDays($day);
                $dow  = $date->dayOfWeek; // 0=Sun … 5=Fri … 6=Sat

                // Friday always off; Saturday alternates by employee
                if ($dow === 5 || ($dow === 6 && ($empIdx + $day) % 3 !== 0)) {
                    [$status, $in, $out, $hours, $ot] = ['holiday', null, null, 0, 0];
                } else {
                    $r = ($empIdx * 31 + $day) % 100;
                    if ($r < 85) {
                        $ot = ($r < 20) ? (($r % 2) + 1) : 0;
                        $status = 'present';
                        $in     = $shift->start_time;
                        $out    = Carbon::parse($shift->end_time)->addHours($ot)->format('H:i');
                        $hours  = $shift->hours + $ot;
                    } elseif ($r < 93) {
                        [$status, $in, $out, $hours, $ot] = ['absent', null, null, 0, 0];
                    } else {
                        $status = 'half_day';
                        $in     = $shift->start_time;
                        $out    = Carbon::parse($shift->start_time)->addHours(4)->format('H:i');
                        [$hours, $ot] = [4, 0];
                    }
                }

                DB::table('attendance')->insert([
                    'employee_id'    => $employee->id,
                    'shift_id'       => $shift->id,
                    'work_date'      => $date->toDateString(),
                    'check_in'       => $in,
                    'check_out'      => $out,
                    'hours_worked'   => $hours,
                    'overtime_hours' => $ot,
                    'status'         => $status,
                    'notes'          => null,
                    'recorded_by'    => $this->adminId,
                    'created_at'     => $date->toDateTimeString(),
                    'updated_at'     => $date->toDateTimeString(),
                ]);
            }
        }
    }

    private function createPayrollRun(array $employees, int $monthsAgo): void
    {
        $target  = Carbon::now()->subMonths($monthsAgo);
        $month   = $target->month;
        $year    = $target->year;
        $payDate = $target->copy()->endOfMonth()->toDateString();
        $dateStr = $target->copy()->endOfMonth()->toDateTimeString();
        $ref     = 'PAY-' . $year . str_pad($month, 2, '0', STR_PAD_LEFT) . '-001';

        $runId = DB::table('payroll_runs')->insertGetId([
            'reference'        => $ref,
            'month'            => $month,
            'year'             => $year,
            'pay_date'         => $payDate,
            'status'           => 'paid',
            'total_gross'      => 0,
            'total_deductions' => 0,
            'total_net'        => 0,
            'notes'            => null,
            'created_by'       => $this->adminId,
            'approved_by'      => $this->adminId,
            'journal_entry_id' => null,
            'created_at'       => $dateStr,
            'updated_at'       => $dateStr,
        ]);

        $totGross = $totDed = $totNet = 0.0;

        foreach ($employees as $employee) {
            if ($employee->pay_type === 'hourly') {
                $gross      = $employee->base_salary * 80;
                $absDeduct  = $otherDeduct = 0.0;
                $daysWorked = 20;
                $daysAbsent = 0;
                $ot         = 0;
            } else {
                $gross      = $employee->base_salary + $employee->housing_allowance
                            + $employee->transport_allowance + $employee->other_allowances;
                $absences   = ($employee->id + $monthsAgo) % 3;
                $absDeduct  = round(($gross / 30) * $absences, 2);
                $otherDeduct = 0.0;
                $daysWorked = 26 - $absences;
                $daysAbsent = $absences;
                $ot         = 0;
            }

            $totalDed = $absDeduct + $otherDeduct;
            $net      = round($gross - $totalDed, 2);

            DB::table('payroll_items')->insert([
                'payroll_run_id'      => $runId,
                'employee_id'         => $employee->id,
                'base_salary'         => $employee->base_salary,
                'housing_allowance'   => $employee->housing_allowance,
                'transport_allowance' => $employee->transport_allowance,
                'other_allowances'    => $employee->other_allowances,
                'overtime_pay'        => 0,
                'absence_deduction'   => $absDeduct,
                'other_deductions'    => $otherDeduct,
                'gross_pay'           => $gross,
                'total_deductions'    => $totalDed,
                'net_pay'             => $net,
                'days_worked'         => $daysWorked,
                'days_absent'         => $daysAbsent,
                'overtime_hours'      => $ot,
                'notes'               => null,
                'created_at'          => $dateStr,
                'updated_at'          => $dateStr,
            ]);

            $totGross += $gross;
            $totDed   += $totalDed;
            $totNet   += $net;
        }

        DB::table('payroll_runs')->where('id', $runId)->update([
            'total_gross'      => round($totGross, 2),
            'total_deductions' => round($totDed, 2),
            'total_net'        => round($totNet, 2),
        ]);
    }
}
