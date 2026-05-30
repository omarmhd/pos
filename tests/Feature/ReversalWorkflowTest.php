<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Reversal;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\JournalEntry;

class ReversalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_reversal_creates_reversal_and_restores_stock()
    {
        // create admin user
        $admin = User::factory()->create(['role' => 'admin']);

        // create product
        $product = Product::create(['name' => 'Test', 'barcode' => '123', 'quantity' => 10, 'cost_price' => 5, 'selling_price' => 10]);

        // create sale with one item
        $sale = Sale::create(['invoice_number' => 'S-1', 'user_id' => $admin->id, 'total_amount' => 10, 'paid_amount' => 10, 'change_amount' => 0]);
        $item = SaleItem::create(['sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => 2, 'unit_price' => 10]);


        // trigger posting by invoking observer manually (ensures JE created)
        (new \App\Observers\SaleObserver())->created($sale);

        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'is_posted' => 1]);

        // call reversal as admin
        $this->actingAs($admin)->post(route('reversals.store'), ['original_type' => Sale::class, 'original_id' => $sale->id]);

        $this->assertDatabaseHas('reversals', ['original_type' => Sale::class, 'original_id' => $sale->id]);

        $product->refresh();
        $this->assertEquals(10, $product->quantity); // restored
    }

    public function test_purchase_reversal_removes_stock_and_creates_reversal()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::create(['name' => 'P', 'barcode' => '321', 'quantity' => 5, 'cost_price' => 2, 'selling_price' => 4]);

        $purchase = Purchase::create(['invoice_number' => 'P-1', 'user_id' => $admin->id, 'total_amount' => 20, 'paid_amount' => 20]);
        PurchaseItem::create(['purchase_id' => $purchase->id, 'product_id' => $product->id, 'quantity' => 3, 'unit_cost' => 2]);

        (new \App\Observers\PurchaseObserver())->created($purchase);

        $this->assertDatabaseHas('purchases', ['id' => $purchase->id, 'is_posted' => 1]);

        $this->actingAs($admin)->post(route('reversals.store'), ['original_type' => Purchase::class, 'original_id' => $purchase->id]);

        $this->assertDatabaseHas('reversals', ['original_type' => Purchase::class, 'original_id' => $purchase->id]);

        $product->refresh();
        $this->assertEquals(5, $product->quantity); // restored to original after reversal
    }

    public function test_cannot_reverse_unposted_record_and_double_reversal_prevented_and_je_balanced()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::create(['name' => 'X', 'barcode' => '999', 'quantity' => 10, 'cost_price' => 1, 'selling_price' => 2]);

        $sale = Sale::create(['invoice_number' => 'S-2', 'user_id' => $admin->id, 'total_amount' => 10, 'paid_amount' => 0, 'change_amount' => 0]);
        SaleItem::create(['sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]);

        // attempt reverse before posting
        $this->actingAs($admin)->post(route('reversals.store'), ['original_type' => Sale::class, 'original_id' => $sale->id]);
        $this->assertDatabaseMissing('reversals', ['original_id' => $sale->id]);

        // now post and reverse
        (new \App\Observers\SaleObserver())->created($sale);
        $this->actingAs($admin)->post(route('reversals.store'), ['original_type' => Sale::class, 'original_id' => $sale->id]);
        $this->assertDatabaseHas('reversals', ['original_id' => $sale->id]);

        // double reversal should be rejected
        $this->actingAs($admin)->post(route('reversals.store'), ['original_type' => Sale::class, 'original_id' => $sale->id]);
        $c = Reversal::where('original_type', Sale::class)->where('original_id', $sale->id)->count();
        $this->assertEquals(1, $c);

        // check JE balance for reversal
        $reversal = Reversal::where('original_type', Sale::class)->where('original_id', $sale->id)->first();
        $je = JournalEntry::find($reversal->reversal_journal_entry_id);
        $this->assertEquals(round($je->debit_total,2), round($je->credit_total,2));
    }
}
