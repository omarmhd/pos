<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InterBranchTransfer extends Model
{
    protected $fillable = [
        'from_branch_id', 'to_branch_id',
        'amount', 'transfer_date', 'reference', 'description',
        'from_journal_entry_id', 'to_journal_entry_id',
        'created_by',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'amount'        => 'decimal:2',
    ];

    public function fromBranch()         { return $this->belongsTo(Branch::class, 'from_branch_id'); }
    public function toBranch()           { return $this->belongsTo(Branch::class, 'to_branch_id'); }
    public function fromJournalEntry()   { return $this->belongsTo(JournalEntry::class, 'from_journal_entry_id'); }
    public function toJournalEntry()     { return $this->belongsTo(JournalEntry::class, 'to_journal_entry_id'); }
    public function createdBy()          { return $this->belongsTo(User::class, 'created_by'); }
}
