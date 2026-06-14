<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * كشف الإيرادات والمصروفات (مستوحى من الأصيل الذهبي):
 * مستند دوري بعضوية حصرية — كل فاتورة تنتمي لكشف واحد فقط
 * (عبر العمود res_statement_id على جداول المستندات).
 */
class RevenueExpenseStatement extends Model
{
    protected $fillable = [
        'number', 'statement_date', 'description',
        'sales_amount', 'sales_tax', 'sales_returns_amount',
        'services_amount', 'services_tax', 'credit_notes_amount',
        'purchases_amount', 'purchases_tax', 'purchase_returns_amount',
        'assets_amount', 'assets_tax', 'customs_amount', 'customs_tax',
        'debit_notes_amount',
        'expenses_amount', 'expenses_tax',
        'net_amount', 'net_vat', 'profit_percent',
        'user_id',
    ];

    protected $casts = [
        'statement_date'          => 'date',
        'sales_amount'            => 'decimal:2',
        'sales_tax'               => 'decimal:2',
        'sales_returns_amount'    => 'decimal:2',
        'services_amount'         => 'decimal:2',
        'services_tax'            => 'decimal:2',
        'credit_notes_amount'     => 'decimal:2',
        'purchases_amount'        => 'decimal:2',
        'purchases_tax'           => 'decimal:2',
        'purchase_returns_amount' => 'decimal:2',
        'assets_amount'           => 'decimal:2',
        'assets_tax'              => 'decimal:2',
        'customs_amount'          => 'decimal:2',
        'customs_tax'             => 'decimal:2',
        'debit_notes_amount'      => 'decimal:2',
        'expenses_amount'         => 'decimal:2',
        'expenses_tax'            => 'decimal:2',
        'net_amount'              => 'decimal:2',
        'net_vat'                 => 'decimal:2',
        'profit_percent'          => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ── أعضاء الكشف (عضوية حصرية) ────────────────────────────────────────────

    public function sales()           { return $this->hasMany(Sale::class,           'res_statement_id'); }
    public function saleReturns()     { return $this->hasMany(SaleReturn::class,     'res_statement_id'); }
    public function purchases()       { return $this->hasMany(Purchase::class,       'res_statement_id'); }
    public function purchaseReturns() { return $this->hasMany(PurchaseReturn::class, 'res_statement_id'); }
    public function expenseInvoices() { return $this->hasMany(ExpenseInvoice::class, 'res_statement_id'); }
    public function fixedAssets()       { return $this->hasMany(FixedAsset::class,         'res_statement_id'); }
    public function customsDeclarations(){ return $this->hasMany(CustomsDeclaration::class, 'res_statement_id'); }
    public function serviceInvoices()   { return $this->hasMany(ServiceInvoice::class,      'res_statement_id'); }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $s) {
            if (empty($s->number)) {
                $s->number = 'PENDING-' . uniqid();
            }
        });

        static::created(function (self $s) {
            if (str_starts_with($s->number, 'PENDING-')) {
                $s->updateQuietly([
                    'number' => sprintf('RES-%s-%04d', now()->format('Ymd'), $s->id % 10000),
                ]);
            }
        });
    }
}
