<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpensePayment extends Model
{
    protected $fillable = [
        'expense_invoice_id', 'user_id', 'branch_id', 'journal_entry_id',
        'payment_date', 'payment_method', 'amount',
        'reference', 'notes',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount'       => 'decimal:2',
    ];

    public function expenseInvoice() { return $this->belongsTo(ExpenseInvoice::class); }
    public function user()           { return $this->belongsTo(User::class); }
    public function branch()         { return $this->belongsTo(Branch::class); }
    public function journalEntry()   { return $this->belongsTo(JournalEntry::class); }
}
