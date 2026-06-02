<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->enum('type', ['retail', 'wholesale', 'vip', 'staff', 'custom'])->default('retail');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_default', 'is_active']);
        });

        // Seed two default price lists: retail (default) + wholesale
        DB::table('price_lists')->insert([
            [
                'code'        => 'RETAIL',
                'name'        => 'سعر التجزئة',
                'type'        => 'retail',
                'description' => 'السعر الافتراضي للبيع للأفراد',
                'is_default'  => true,
                'is_active'   => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'code'        => 'WHOLESALE',
                'name'        => 'سعر الجملة',
                'type'        => 'wholesale',
                'description' => 'سعر الجملة للتجار والموزعين',
                'is_default'  => false,
                'is_active'   => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ]);

        // Save default price list id to settings
        $retailId = DB::table('price_lists')->where('code', 'RETAIL')->value('id');
        DB::table('settings')->updateOrInsert(
            ['key' => 'default_price_list_id'],
            ['value' => $retailId, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'default_price_list_id')->delete();
        Schema::dropIfExists('price_lists');
    }
};
