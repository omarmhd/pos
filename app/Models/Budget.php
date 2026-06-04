<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    protected $fillable = ['name', 'year', 'branch_id', 'is_active', 'notes', 'created_by'];
    protected $casts    = ['is_active' => 'boolean'];

    public function branch()    { return $this->belongsTo(Branch::class); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function lines()     { return $this->hasMany(BudgetLine::class); }
}
