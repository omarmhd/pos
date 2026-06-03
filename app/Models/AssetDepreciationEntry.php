<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetDepreciationEntry extends Model
{
    protected $fillable = [
        'fixed_asset_id', 'journal_entry_id', 'branch_id',
        'period_year', 'period_month',
        'depreciation_amount',
        'accumulated_before', 'accumulated_after',
        'net_book_value_after',
        'notes',
    ];

    protected $casts = [
        'depreciation_amount'  => 'decimal:2',
        'accumulated_before'   => 'decimal:2',
        'accumulated_after'    => 'decimal:2',
        'net_book_value_after' => 'decimal:2',
    ];

    public function asset()        { return $this->belongsTo(FixedAsset::class, 'fixed_asset_id'); }
    public function journalEntry() { return $this->belongsTo(JournalEntry::class); }
    public function branch()       { return $this->belongsTo(Branch::class); }

    public function periodLabel(): string
    {
        $months = [
            1=>'يناير',2=>'فبراير',3=>'مارس',4=>'أبريل',
            5=>'مايو',6=>'يونيو',7=>'يوليو',8=>'أغسطس',
            9=>'سبتمبر',10=>'أكتوبر',11=>'نوفمبر',12=>'ديسمبر',
        ];
        return ($months[$this->period_month] ?? $this->period_month) . ' ' . $this->period_year;
    }
}
