<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ميزات المخازن المستوحاة من مقارنة "الأصيل الذهبي":
 *  1. وحدات متعددة للصنف (product_units) بمعامل تحويل وباركود وسعر لكل وحدة
 *  2. الحد الأقصى وحد إعادة الطلب (products.max_quantity / reorder_level)
 *  3. معادلات التصنيع (product_components) + مستندات التصنيع (assemblies)
 *  4. أنواع الأصناف (products.product_type: goods/service/bundle)
 *  5. الكمية الإضافية - البونص (bonus_after_qty / bonus_every_qty / bonus_free_qty)
 *  6. قوائم أسعار الشراء للموردين (purchase_price_lists + purchase_product_prices)
 *  7. ضريبة لكل صنف (products.is_taxable / vat_rate) + ضريبة لكل سطر بيع
 *  8. العملات (currencies) وأسعار بعملة أجنبية على الصنف
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 8. العملات ──────────────────────────────────────────────────────
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();          // EGP, USD, SAR ...
            $table->string('name', 50);                     // جنيه مصري
            $table->string('symbol', 10);                   // ج.م
            $table->decimal('exchange_rate', 14, 6)->default(1); // كم وحدة من العملة الأساسية تساوي 1 من هذه العملة
            $table->boolean('is_base')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // عملة أساسية افتراضية من إعدادات النظام
        $symbol = DB::table('settings')->where('key', 'currency_symbol')->value('value') ?? 'ج.م';
        DB::table('currencies')->insert([
            'code' => 'BASE', 'name' => 'العملة الأساسية', 'symbol' => $symbol,
            'exchange_rate' => 1, 'is_base' => true, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // ── 2 + 4 + 5 + 7 + 8. حقول جديدة على الأصناف ───────────────────────
        Schema::table('products', function (Blueprint $table) {
            $table->string('product_type', 20)->default('goods')->after('category_id'); // goods | service | bundle
            $table->decimal('max_quantity', 12, 3)->nullable()->after('min_quantity');
            $table->decimal('reorder_level', 12, 3)->nullable()->after('max_quantity');
            $table->boolean('is_taxable')->default(true)->after('selling_price');
            $table->decimal('vat_rate', 5, 2)->nullable()->after('is_taxable'); // null = النسبة العامة
            $table->decimal('bonus_after_qty', 12, 3)->nullable()->after('vat_rate'); // إضافي بعد الكمية
            $table->decimal('bonus_every_qty', 12, 3)->nullable()->after('bonus_after_qty'); // إضافي كل كمية
            $table->decimal('bonus_free_qty', 12, 3)->default(1)->after('bonus_every_qty'); // الكمية المجانية
            $table->foreignId('currency_id')->nullable()->after('bonus_free_qty')
                  ->constrained('currencies')->nullOnDelete();
            $table->decimal('cost_price_fc', 12, 4)->nullable()->after('currency_id');    // سعر الشراء بالعملة الأجنبية
            $table->decimal('selling_price_fc', 12, 4)->nullable()->after('cost_price_fc'); // سعر البيع بالعملة الأجنبية
            $table->index('product_type');
        });

        // ── 1. وحدات متعددة للصنف ───────────────────────────────────────────
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);                         // كرتون، دستة، شد ...
            $table->decimal('factor', 14, 4);                   // عدد الوحدات الرئيسية في هذه الوحدة
            $table->string('barcode', 64)->nullable()->unique(); // باركود خاص بالوحدة
            $table->decimal('selling_price', 12, 2)->nullable(); // سعر بيع الوحدة (فارغ = سعر الأساس × المعامل)
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('product_id');
        });

        // ── 3. معادلة التصنيع / مكونات الصنف التجميعي ───────────────────────
        Schema::create('product_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();   // الصنف الأب (مصنّع/تجميعي)
            $table->foreignId('component_id')->constrained('products')->restrictOnDelete(); // المكوّن
            $table->decimal('quantity', 14, 4);                 // الكمية المستهلكة لإنتاج وحدة واحدة
            $table->timestamps();
            $table->unique(['product_id', 'component_id']);
        });

        // ── 3. مستندات التصنيع ──────────────────────────────────────────────
        Schema::create('assemblies', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->date('assembly_date');
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->decimal('quantity', 12, 3);                 // الكمية المنتجة
            $table->decimal('unit_cost', 12, 4)->default(0);    // تكلفة الوحدة المنتجة
            $table->decimal('total_cost', 12, 2)->default(0);   // إجمالي تكلفة المكونات
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->boolean('is_posted')->default(false);
            $table->timestamps();
        });

        Schema::create('assembly_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assembly_id')->constrained()->cascadeOnDelete();
            $table->foreignId('component_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 14, 4);                 // إجمالي الكمية المستهلكة
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->timestamps();
        });

        // ── 6. قوائم أسعار الشراء ───────────────────────────────────────────
        Schema::create('purchase_price_lists', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('purchase_product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_price_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('cost_price', 12, 2);
            $table->timestamps();
            $table->unique(['purchase_price_list_id', 'product_id'], 'ppp_list_product_unique');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->foreignId('purchase_price_list_id')->nullable()
                  ->constrained('purchase_price_lists')->nullOnDelete();
        });

        // ── 1 + 5 + 7. حقول سطر البيع ───────────────────────────────────────
        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('vat_rate', 5, 2)->default(0)->after('total_price');
            $table->decimal('vat_amount', 12, 2)->default(0)->after('vat_rate');
            $table->decimal('bonus_qty', 12, 3)->default(0)->after('vat_amount');   // كمية مجانية صرفت مع السطر
            $table->foreignId('product_unit_id')->nullable()->after('bonus_qty')
                  ->constrained('product_units')->nullOnDelete();
            $table->decimal('unit_factor', 14, 4)->default(1)->after('product_unit_id');
            $table->string('unit_label', 50)->nullable()->after('unit_factor');
        });

        // ── 1. حقول سطر الشراء ──────────────────────────────────────────────
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->foreignId('product_unit_id')->nullable()
                  ->constrained('product_units')->nullOnDelete();
            $table->decimal('unit_factor', 14, 4)->default(1);
            $table->string('unit_label', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_unit_id');
            $table->dropColumn(['unit_factor', 'unit_label']);
        });
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_unit_id');
            $table->dropColumn(['vat_rate', 'vat_amount', 'bonus_qty', 'unit_factor', 'unit_label']);
        });
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_price_list_id');
        });
        Schema::dropIfExists('purchase_product_prices');
        Schema::dropIfExists('purchase_price_lists');
        Schema::dropIfExists('assembly_items');
        Schema::dropIfExists('assemblies');
        Schema::dropIfExists('product_components');
        Schema::dropIfExists('product_units');
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('currency_id');
            $table->dropColumn([
                'product_type', 'max_quantity', 'reorder_level', 'is_taxable', 'vat_rate',
                'bonus_after_qty', 'bonus_every_qty', 'bonus_free_qty',
                'cost_price_fc', 'selling_price_fc',
            ]);
        });
        Schema::dropIfExists('currencies');
    }
};
