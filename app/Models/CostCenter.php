<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostCenter extends Model
{
    protected $fillable = ['code', 'name', 'branch_id', 'notes', 'is_active'];
    protected $casts    = ['is_active' => 'boolean'];

    public function branch()           { return $this->belongsTo(Branch::class); }
    public function journalEntryLines(){ return $this->hasMany(JournalEntryLine::class); }
}
