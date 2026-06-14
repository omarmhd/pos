<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssemblyItem extends Model
{
    protected $fillable = [
        'assembly_id', 'component_id', 'quantity', 'unit_cost', 'total_cost',
    ];

    protected $casts = [
        'quantity'   => 'decimal:4',
        'unit_cost'  => 'decimal:4',
        'total_cost' => 'decimal:2',
    ];

    public function assembly()
    {
        return $this->belongsTo(Assembly::class);
    }

    public function component()
    {
        return $this->belongsTo(Product::class, 'component_id');
    }
}
