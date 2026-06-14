<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CustomsDeclaration;
use App\Models\JournalEntryLine;
use App\Models\Product;
use App\Models\RevenueExpenseStatement;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\ServiceInvoice;
use App\Models\Supplier;
use App\Models\User;
use App\Services\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * اختبارات فروقات الأصيل المُنفَّذة:
 *  - فاتورة إيراد الخدمات (IFRS 15) — ترحيل GL متوازن.
 *  - الدفع المختلط بالشيكات — جزء الشيك يُرحَّل لحساب شيكات تحت التحصيل.
 *  - كشف الإيرادات والمصروفات — ضريبة مدخلات الجمارك + ضريبة مخرجات الخدمات.
 *  - رقم التسجيل الضريبي (TRN) للمورد.
 */
class InvoiceGapsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // دليل حسابات أدنى لازم للترحيل
        $accounts = [
            ['1000', 'Cash', 'asset'],
            ['1120', 'Checks Receivable', 'asset'],
            ['1200', 'Accounts Receivable', 'asset'],
            ['1260', 'Input VAT', 'asset'],
            ['1300', 'Inventory', 'asset'],
            ['2000', 'Accounts Payable', 'liability'],
            ['2200', 'Tax Payable (Output VAT)', 'liability'],
            ['4000', 'Sales Revenue', 'revenue'],
            ['4200', 'Service Revenue', 'revenue'],
            ['4300', 'Sales Discount', 'revenue'],
            ['5000', 'COGS', 'expense'],
        ];
        foreach ($accounts as [$code, $name, $type]) {
            Account::create(['code' => $code, 'name' => $name, 'type' => $type, 'is_active' => true]);
        }
    }

    /** فاتورة إيراد خدمات نقدية: مدين نقدية = الإجمالي، دائن إيراد خدمات = الصافي + ض.ق.م مخرجات */
    public function test_service_invoice_posts_balanced_gl_ifrs15(): void
    {
        $user = User::factory()->create();

        $invoice = ServiceInvoice::create([
            'invoice_date' => now()->toDateString(),
            'total_amount' => 115,
            'tax_amount'   => 15,
            'is_credit'    => false,
            'user_id'      => $user->id,
        ]);

        $je = app(LedgerPostingService::class)->postServiceInvoice($invoice);

        $lines   = JournalEntryLine::where('journal_entry_id', $je->id)->get();
        $debits  = round((float) $lines->sum('debit'), 2);
        $credits = round((float) $lines->sum('credit'), 2);

        $this->assertEqualsWithDelta($debits, $credits, 0.01, 'القيد غير متوازن');
        $this->assertEqualsWithDelta(115, $debits, 0.01);

        $service = Account::where('code', '4200')->first();
        $tax     = Account::where('code', '2200')->first();
        $cash    = Account::where('code', '1000')->first();

        $this->assertTrue($lines->firstWhere('account_id', $service->id)->credit == 100.00);
        $this->assertTrue($lines->firstWhere('account_id', $tax->id)->credit == 15.00);
        $this->assertTrue($lines->firstWhere('account_id', $cash->id)->debit == 115.00);

        $invoice->refresh();
        $this->assertTrue($invoice->is_posted);
    }

    /** بيع نقدي مدفوع بالكامل شيكاً: جزء الشيك يُرحَّل لحساب شيكات تحت التحصيل (1130) لا النقدية */
    public function test_sale_with_cheque_debits_checks_receivable(): void
    {
        $user    = User::factory()->create();
        $product = Product::create(['name' => 'P', 'cost_price' => 50, 'selling_price' => 100, 'quantity' => 100]);

        $sale = Sale::create([
            'invoice_number' => 'INV-CHK-1',
            'user_id'        => $user->id,
            'subtotal'       => 100,
            'discount'       => 0,
            'tax'            => 15,
            'total_amount'   => 115,
            'payment_method' => 'cash',
            'paid_amount'    => 0,
            'cash_amount'    => 0,
            'cheque_amount'  => 115,
            'is_credit'      => false,
        ]);
        SaleItem::create([
            'sale_id' => $sale->id, 'product_id' => $product->id,
            'quantity' => 1, 'unit_price' => 100, 'cost_price' => 50, 'total_price' => 100,
        ]);

        $je = app(LedgerPostingService::class)->postSale($sale->load('items', 'customer'));

        $lines = JournalEntryLine::where('journal_entry_id', $je->id)->get();
        $this->assertEqualsWithDelta($lines->sum('debit'), $lines->sum('credit'), 0.01);

        $checks = Account::where('code', '1120')->first();
        $cash   = Account::where('code', '1000')->first();

        // الشيك يدخل حساب الشيكات تحت التحصيل بكامل المبلغ
        $this->assertTrue((float) $lines->where('account_id', $checks->id)->sum('debit') == 115.00);
        // ولا نقدية مدينة
        $this->assertTrue((float) $lines->where('account_id', $cash->id)->sum('debit') == 0.00);
    }

    /** كشف الإيرادات والمصروفات: صافي ض.ق.م = مخرجات الخدمات − مدخلات الجمارك */
    public function test_res_statement_net_vat_includes_customs_input_and_service_output(): void
    {
        Gate::before(fn() => true);   // تجاوز الصلاحيات في الاختبار
        $user   = User::factory()->create();
        $cutoff = now()->toDateString();

        $customs = CustomsDeclaration::create([
            'declaration_date' => $cutoff,
            'total_amount'     => 500,
            'tax_amount'       => 75,   // ضريبة مدخلات الواردات
            'user_id'          => $user->id,
        ]);
        $service = ServiceInvoice::create([
            'invoice_date' => $cutoff,
            'total_amount' => 230,
            'tax_amount'   => 30,       // ضريبة مخرجات الخدمات
            'is_credit'    => false,
            'is_posted'    => true,
            'user_id'      => $user->id,
        ]);

        $this->actingAs($user)->post(route('res.store'), [
            'statement_date' => $cutoff,
            'include' => [
                'customs_declarations' => [$customs->id],
                'service_invoices'     => [$service->id],
            ],
        ])->assertRedirect();

        $st = RevenueExpenseStatement::first();
        $this->assertNotNull($st);
        $this->assertEqualsWithDelta(75, (float) $st->customs_tax, 0.01);
        // net_vat = 30 (مخرجات) − 75 (مدخلات جمارك) = −45
        $this->assertEqualsWithDelta(-45, (float) $st->net_vat, 0.01);
    }

    /** تجيير شيك وارد لمورد: مدين ذمم الموردين / دائن شيكات تحت التحصيل، والحالة "مُجيَّر" */
    public function test_cheque_endorsement_to_supplier(): void
    {
        $user     = User::factory()->create();
        $supplier = Supplier::create(['name' => 'مورّد', 'phone' => '0599']);

        $check = \App\Models\Check::create([
            'type'       => 'receivable',
            'check_date' => now()->toDateString(),
            'due_date'   => now()->addDays(30)->toDateString(),
            'amount'     => 500,
            'party_name' => 'عميل',
            'status'     => 'received',
            'user_id'    => $user->id,
        ]);

        $svc = new \App\Services\CheckPostingService();
        $svc->postReceived($check);
        $je = $svc->postEndorsed($check->fresh(), $supplier->id);

        $lines = JournalEntryLine::where('journal_entry_id', $je->id)->get();
        $this->assertEqualsWithDelta($lines->sum('debit'), $lines->sum('credit'), 0.01);

        $ap     = Account::where('code', '2000')->first();
        $checks = Account::where('code', '1120')->first();
        $this->assertTrue((float) $lines->where('account_id', $ap->id)->sum('debit') == 500.00);
        $this->assertTrue((float) $lines->where('account_id', $checks->id)->sum('credit') == 500.00);

        $check->refresh();
        $this->assertEquals('endorsed', $check->status);
        $this->assertEquals($supplier->id, $check->endorsed_to_supplier_id);
    }

    /** إعادة إيداع شيك مرتدّ: bounced → received مع DR شيكات تحت التحصيل / CR ذمم */
    public function test_cheque_represent_after_bounce(): void
    {
        $user = User::factory()->create();

        $check = \App\Models\Check::create([
            'type'       => 'receivable',
            'check_date' => now()->toDateString(),
            'due_date'   => now()->addDays(30)->toDateString(),
            'amount'     => 300,
            'party_name' => 'عميل',
            'status'     => 'received',
            'user_id'    => $user->id,
        ]);

        $svc = new \App\Services\CheckPostingService();
        $svc->postReceived($check);
        $svc->postBounced($check->fresh());            // → bounced
        $je = $svc->postRepresented($check->fresh());  // → received مجدداً

        $check->refresh();
        $this->assertEquals('received', $check->status);

        $lines = JournalEntryLine::where('journal_entry_id', $je->id)->get();
        $this->assertEqualsWithDelta($lines->sum('debit'), $lines->sum('credit'), 0.01);

        $chr = Account::where('code', '1120')->first();
        $this->assertTrue((float) $lines->where('account_id', $chr->id)->sum('debit') == 300.00);
    }

    /** رقم التسجيل الضريبي (TRN) يُحفظ على المورد */
    public function test_supplier_tax_number_persists(): void
    {
        $supplier = Supplier::create([
            'name'       => 'مورّد',
            'phone'      => '0599',
            'tax_number' => 'TRN-123456789',
        ]);

        $this->assertDatabaseHas('suppliers', [
            'id'         => $supplier->id,
            'tax_number' => 'TRN-123456789',
        ]);
    }
}
