<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Account;
use App\Models\Product;
use App\Models\User;
use App\Models\Sale;
use App\Models\SaleItem;

class SaleAccountingTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_creates_journal_and_stock_movement()
    {
        // seed accounts
        $sales = Account::create(['code' => '4000', 'name' => 'Sales', 'type' => 'revenue', 'is_active' => true]);
        $cogs = Account::create(['code' => '5000', 'name' => 'COGS', 'type' => 'expense', 'is_active' => true]);
        $inv = Account::create(['code' => '1300', 'name' => 'Inventory', 'type' => 'asset', 'is_active' => true]);
        $cash = Account::create(['code' => '1000', 'name' => 'Cash', 'type' => 'asset', 'is_active' => true]);

        $user = User::factory()->create();

        $product = Product::create(['name' => 'Test', 'cost_price' => 10, 'selling_price' => 20, 'quantity' => 100]);

        // create sale
        $sale = Sale::create(['invoice_number' => 'INV-1', 'user_id' => $user->id, 'total_amount' => 40, 'paid_amount' => 40, 'payment_method' => 'cash']);
        SaleItem::create(['sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => 2, 'unit_price' => 20, 'total_price' => 40]);

        // reload product
        $product->refresh();
        $this->assertEquals(98, $product->quantity);

        $this->assertDatabaseHas('journal_entries', ['source_type' => Sale::class, 'source_id' => $sale->id]);
        $this->assertDatabaseHas('journal_entry_lines', ['debit' => 20.00]);
    }
}
