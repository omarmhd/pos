<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    protected $fillable = ['name', 'start_time', 'end_time', 'hours', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function attendance()
    {
        return $this->hasMany(Attendance::class);
    }
}
