<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'entry_number',
        'entry_date',
        'reference',
        'source_type',
        'source_id',
        'description',
        'user_id',
        'posted_at',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'posted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lines()
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function getDebitTotalAttribute(): float
    {
        return (float) $this->lines()->sum('debit');
    }

    public function getCreditTotalAttribute(): float
    {
        return (float) $this->lines()->sum('credit');
    }

    public function getIsBalancedAttribute(): bool
    {
        return abs($this->debit_total - $this->credit_total) < 0.01;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($entry) {
            // Temporary unique placeholder — column is NOT NULL so we need a value.
            // Replaced immediately after insert in created() using the real auto-increment id.
            $entry->entry_number = 'PENDING-' . uniqid('', true);
        });

        static::created(function ($entry) {
            $date = $entry->entry_date instanceof \Carbon\Carbon
                ? $entry->entry_date
                : \Carbon\Carbon::parse($entry->entry_date ?? now());
            $entry->updateQuietly([
                'entry_number' => 'JE-' . $date->format('Ymd') . '-' . str_pad((string) $entry->id, 6, '0', STR_PAD_LEFT),
            ]);
        });
    }
}