<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reversal extends Model
{
    use HasFactory;

    protected $fillable = [
        'original_type', 'original_id', 'reversal_journal_entry_id', 'reason', 'created_by'
    ];

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_journal_entry_id');
    }

    public function original()
    {
        return $this->morphTo(null, 'original_type', 'original_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
