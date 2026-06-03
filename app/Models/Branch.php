<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = [
        'code', 'name', 'type',
        'address', 'phone',
        'is_default', 'is_active', 'notes',
        'price_list_id',
        'cash_account_id',
        'bank_account_id',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
    ];

    public static array $types = [
        'head_office' => 'المقر الرئيسي',
        'retail'      => 'فرع تجزئة',
        'wholesale'   => 'فرع جملة',
        'warehouse'   => 'مستودع',
        'showroom'    => 'معرض',
    ];

    public function typeLabel(): string
    {
        return self::$types[$this->type] ?? $this->type;
    }

    public function cashAccount()   { return $this->belongsTo(Account::class, 'cash_account_id'); }
    public function bankAccount()   { return $this->belongsTo(Account::class, 'bank_account_id'); }
    public function priceList()     { return $this->belongsTo(PriceList::class); }
    public function warehouses()    { return $this->hasMany(Warehouse::class); }
    public function users()         { return $this->hasMany(User::class); }

    public function activeWarehouses()
    {
        return $this->hasMany(Warehouse::class)->where('is_active', true);
    }

    public function defaultWarehouse(): ?Warehouse
    {
        return $this->warehouses()->where('is_default', true)->first()
            ?? $this->warehouses()->where('is_active', true)->first();
    }

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (Branch $branch) {
            if ($branch->is_default) {
                static::where('id', '!=', $branch->id ?? 0)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });

        // Auto-create cash and bank accounts when a new branch is created
        static::created(function (Branch $branch) {
            \App\Services\BranchAccountingService::setupAccounts($branch);
        });
    }
}
