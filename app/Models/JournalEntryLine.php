<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Account;
use App\Models\JournalEntry;

class JournalEntryLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'party_type',
        'party_id',
        'debit',
        'credit',
        'line_description',
        'cost_center_id',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function entry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function costCenter()
    {
        return $this->belongsTo(CostCenter::class);
    }

    /** الطرف المرتبط (عميل/مورّد/موظف) — أستاذ مساعد فوق حساب المراقبة */
    public function party()
    {
        return $this->morphTo();
    }

    /** اسم الطرف المختصر للعرض */
    public function partyLabel(): ?string
    {
        if (!$this->party_type) return null;
        $name = $this->party?->name ?? ('#' . $this->party_id);
        $kind = match (class_basename($this->party_type)) {
            'Customer' => 'عميل',
            'Supplier' => 'مورّد',
            'Employee' => 'موظف',
            default    => 'طرف',
        };
        return $kind . ': ' . $name;
    }
}