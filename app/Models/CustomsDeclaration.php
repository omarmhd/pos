<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * الإقرار الجمركي (Customs Declaration / Import VAT).
 * يسجّل قيمة الواردات والرسوم الجمركية (تكلفة) وضريبة القيمة المضافة على الواردات
 * (ضريبة مدخلات) — تُدرج في كشف الإيرادات والمصروفات بعضوية حصرية.
 */
class CustomsDeclaration extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'declaration_number', 'declaration_date',
        'supplier_id', 'vendor_name', 'customs_ref',
        'total_amount', 'tax_amount', 'notes',
        'user_id', 'branch_id', 'res_statement_id',
    ];

    protected $casts = [
        'declaration_date' => 'date',
        'total_amount'     => 'decimal:2',
        'tax_amount'       => 'decimal:2',
    ];

    public function supplier()     { return $this->belongsTo(Supplier::class); }
    public function user()         { return $this->belongsTo(User::class); }
    public function branch()       { return $this->belongsTo(Branch::class); }
    public function resStatement() { return $this->belongsTo(RevenueExpenseStatement::class, 'res_statement_id'); }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $d) {
            $d->declaration_number = 'PENDING-' . uniqid('', true);
        });

        static::created(function (self $d) {
            $d->updateQuietly([
                'declaration_number' => 'CUST-' . date('Ymd') . '-' . str_pad((string) $d->id, 4, '0', STR_PAD_LEFT),
            ]);
        });
    }
}
