<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedAsset extends Model
{
    protected $fillable = [
        'asset_code', 'name', 'description',
        'category_id', 'branch_id', 'user_id', 'journal_entry_id',
        'purchase_date', 'purchase_cost', 'tax_amount', 'residual_value', 'supplier_name',
        'depreciation_method', 'useful_life_months', 'depreciation_rate',
        'accumulated_depreciation', 'net_book_value',
        'status', 'disposal_date', 'disposal_amount', 'notes',
        'res_statement_id',
    ];

    protected $casts = [
        'purchase_date'           => 'date',
        'disposal_date'           => 'date',
        'purchase_cost'           => 'decimal:2',
        'tax_amount'              => 'decimal:2',
        'residual_value'          => 'decimal:2',
        'accumulated_depreciation'=> 'decimal:2',
        'net_book_value'          => 'decimal:2',
        'disposal_amount'         => 'decimal:2',
        'depreciation_rate'       => 'decimal:4',
    ];

    public function category()       { return $this->belongsTo(FixedAssetCategory::class, 'category_id'); }
    public function branch()         { return $this->belongsTo(Branch::class); }
    public function user()           { return $this->belongsTo(User::class); }
    public function journalEntry()   { return $this->belongsTo(JournalEntry::class); }
    public function depreciationEntries() { return $this->hasMany(AssetDepreciationEntry::class, 'fixed_asset_id'); }
    public function resStatement()   { return $this->belongsTo(RevenueExpenseStatement::class, 'res_statement_id'); }

    /** Depreciable amount = cost - residual value */
    public function depreciableAmount(): float
    {
        return max(0, (float) $this->purchase_cost - (float) $this->residual_value);
    }

    /** Remaining months of useful life based on entries posted */
    public function remainingMonths(): int
    {
        $posted = $this->depreciationEntries()->count();
        return max(0, $this->useful_life_months - $posted);
    }

    /** True when fully depreciated (net book value = residual value) */
    public function isFullyDepreciated(): bool
    {
        return (float) $this->net_book_value <= (float) $this->residual_value + 0.005;
    }

    /** Monthly straight-line depreciation amount */
    public function monthlyDepreciationSL(): float
    {
        return round($this->depreciableAmount() / max(1, $this->useful_life_months), 2);
    }

    /** Monthly declining-balance depreciation based on current NBV */
    public function monthlyDepreciationDB(): float
    {
        $annualRate   = $this->depreciation_rate
            ?? round(1 / ($this->useful_life_months / 12), 4);
        $monthlyRate  = $annualRate / 12;
        $depr         = round((float) $this->net_book_value * $monthlyRate, 2);
        $maxAllowed   = max(0, (float) $this->net_book_value - (float) $this->residual_value);
        return min($depr, $maxAllowed);
    }

    /** Calculate depreciation amount for the next period */
    public function nextPeriodDepreciation(): float
    {
        if ($this->status !== 'active' || $this->isFullyDepreciated()) {
            return 0.0;
        }
        return match($this->depreciation_method) {
            'declining_balance' => $this->monthlyDepreciationDB(),
            default             => $this->monthlyDepreciationSL(),
        };
    }

    public function statusLabel(): string
    {
        return match($this->status) {
            'active'             => 'نشط',
            'fully_depreciated'  => 'مستهلك بالكامل',
            'disposed'           => 'مُستبعَد',
            default              => $this->status,
        };
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (FixedAsset $asset) {
            if (!$asset->asset_code || str_starts_with($asset->asset_code, 'PENDING-')) {
                $asset->asset_code = 'PENDING-' . uniqid('', true);
            }
            // net_book_value starts at purchase_cost
            $asset->net_book_value = $asset->purchase_cost;
        });

        static::created(function (FixedAsset $asset) {
            $asset->updateQuietly([
                'asset_code' => 'FA-' . str_pad((string) $asset->id, 4, '0', STR_PAD_LEFT),
            ]);
        });
    }
}
