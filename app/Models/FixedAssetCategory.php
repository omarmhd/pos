<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedAssetCategory extends Model
{
    protected $fillable = [
        'code', 'name',
        'asset_account_id',
        'accumulated_dep_account_id',
        'depreciation_expense_account_id',
        'depreciation_method',
        'useful_life_months',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static array $methods = [
        'straight_line'      => 'القسط الثابت (Straight Line)',
        'declining_balance'  => 'القسط المتناقص (Declining Balance)',
    ];

    public function assetAccount()             { return $this->belongsTo(Account::class, 'asset_account_id'); }
    public function accumulatedDepAccount()    { return $this->belongsTo(Account::class, 'accumulated_dep_account_id'); }
    public function depreciationExpAccount()   { return $this->belongsTo(Account::class, 'depreciation_expense_account_id'); }
    public function assets()                   { return $this->hasMany(FixedAsset::class, 'category_id'); }

    public function usefulLifeLabel(): string
    {
        $years  = intdiv($this->useful_life_months, 12);
        $months = $this->useful_life_months % 12;
        $parts  = [];
        if ($years)  $parts[] = $years . ' سنة';
        if ($months) $parts[] = $months . ' شهر';
        return implode(' و', $parts) ?: '—';
    }
}
