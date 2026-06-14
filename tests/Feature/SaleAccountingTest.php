<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\JournalEntryLine;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ترحيل فاتورة بيع نقدية عبر المسار المعتمد (LedgerPostingService::postSale).
 * (أُعيدت كتابته بعد تحييد SaleObserver المزدوج — تدقيق C-2/C-3.)
 */
class SaleAccountingTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_sale_posts_balanced_journal_via_service(): void
    {
        // دليل حسابات أدنى
        Account::create(['code' => '1000', 'name' => 'Cash',      'type' => 'asset',   'is_active' => true]);
        Account::create(['code' => '1300', 'name' => 'Inventory', 'type' => 'asset',   'is_active' => true]);
        Account::create(['code' => '4000', 'name' => 'Sales',     'type' => 'revenue', 'is_active' => true]);
        Account::create(['code' => '5000', 'name' => 'COGS',      'type' => 'expense', 'is_active' => true]);

        $user    = User::factory()->create();
        $product = Product::create(['name' => 'Test', 'cost_price' => 10, 'selling_price' => 20, 'quantity' => 100]);

        $sale = Sale::create([
            'invoice_number' => 'INV-1',
            'user_id'        => $user->id,
            'subtotal'       => 40,
            'discount'       => 0,
            'tax'            => 0,
            'total_amount'   => 40,
            'payment_method' => 'cash',
            'paid_amount'    => 40,
            'is_credit'      => false,
        ]);
        SaleItem::create([
            'sale_id' => $sale->id, 'product_id' => $product->id,
            'quantity' => 2, 'unit_price' => 20, 'cost_price' => 10, 'total_price' => 40,
        ]);

        $je = app(LedgerPostingService::class)->postSale($sale->load('items', 'customer'));

        $this->assertDatabaseHas('journal_entries', ['source_type' => Sale::class, 'source_id' => $sale->id]);

        $lines = JournalEntryLine::where('journal_entry_id', $je->id)->get();
        $this->assertEqualsWithDelta($lines->sum('debit'), $lines->sum('credit'), 0.01, 'القيد غير متوازن');

        $cash  = Account::where('code', '1000')->first();
        $sales = Account::where('code', '4000')->first();
        $cogs  = Account::where('code', '5000')->first();

        $this->assertTrue((float) $lines->where('account_id', $cash->id)->sum('debit')  == 40.00);
        $this->assertTrue((float) $lines->where('account_id', $sales->id)->sum('credit') == 40.00);
        $this->assertTrue((float) $lines->where('account_id', $cogs->id)->sum('debit')  == 20.00);

        $sale->refresh();
        $this->assertTrue($sale->is_posted);
    }
}
