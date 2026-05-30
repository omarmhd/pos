<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'value'];

    public static function get($key, $default = null)
    {
        $value = cache()->remember("setting:{$key}", now()->addDay(), function () use ($key) {
            $rec = static::where('key', $key)->first();
            return $rec ? $rec->value : null;
        });

        return $value ?? $default;
    }

    public static function set($key, $value)
    {
        cache()->forget("setting:{$key}");
        return static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
