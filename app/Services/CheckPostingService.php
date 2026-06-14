<?php

namespace App\Services;

use App\Models\Check;
use App\Models\JournalEntry;
use App\Models\Setting;
use RuntimeException;

/**
 * CheckPostingService
 * يُرحِّل القيود المحاسبية لشيكات القبض والصرف
 * عند كل تحوّل في حالة الشيك.
 *
 * Receivable lifecycle:
 *   received  → Dr. شيكات تحت التحصيل (1120) / Cr. ذمم العملاء (1200)
 *   deposited → Dr. البنك (1100)               / Cr. شيكات تحت التحصيل (1120)
 *   cleared   → لا قيد إضافي (المبلغ بات في البنك)
 *   bounced   → Dr. ذمم العملاء (1200)         / Cr. البنك / شيكات تحت التحصيل
 *
 * Payable lifecycle:
 *   pending   → Dr. ذمم الموردين (2000)        / Cr. شيكات مستحقة الدفع (2030)
 *   cleared   → Dr. شيكات مستحقة الدفع (2030)  / Cr. البنك (1100)
 *   returned  → Dr. شيكات مستحقة الدفع (2030)  / Cr. ذمم الموردين (2000)
 */
class CheckPostingService
{
    private LedgerPostingService $ledger;

    public function __construct()
    {
        $this->ledger = new LedgerPostingService();
    }

    // ── Receivable ─────────────────────────────────────────────────────────────

    /**
     * عند استلام الشيك من العميل (received)
     * Dr. شيكات تحت التحصيل / Cr. ذمم العملاء
     */
    public function postReceived(Check $check): JournalEntry
    {
        $this->assertType($check, 'receivable');

        $chrCode = Setting::get('account_checks_receivable_code', '1120');
        $arCode  = Setting::get('account_ar_code', '1200');

        $chrAcct = $this->resolveAccount($chrCode, 'شيكات تحت التحصيل');
        $arAcct  = $this->resolveAccount($arCode,  'ذمم العملاء');

        $entry = $this->buildEntry($check, 'استلام شيك وارد — ' . $check->check_number, [
            ['account_id' => $chrAcct->id, 'debit' => $check->amount, 'credit' => 0,
             'line_description' => 'شيكات تحت التحصيل — ' . $check->partyName()],
            ['account_id' => $arAcct->id,  'debit' => 0, 'credit' => $check->amount,
             'line_description' => 'ذمم العملاء — ' . $check->partyName()],
        ]);

        $check->updateQuietly(['journal_entry_id' => $entry->id, 'status' => 'received']);
        return $entry;
    }

    /**
     * عند إيداع الشيك في البنك (deposited)
     * Dr. البنك / Cr. شيكات تحت التحصيل
     */
    public function postDeposited(Check $check): JournalEntry
    {
        $this->assertType($check, 'receivable');
        $this->assertStatus($check, ['received']);

        $chrCode  = Setting::get('account_checks_receivable_code', '1120');
        $bankCode = Setting::get('account_bank_code', '1100');

        $chrAcct  = $this->resolveAccount($chrCode,  'شيكات تحت التحصيل');
        $bankAcct = $this->resolveAccount($bankCode,  'البنك');

        $entry = $this->buildEntry($check, 'إيداع شيك في البنك — ' . $check->check_number, [
            ['account_id' => $bankAcct->id, 'debit' => $check->amount, 'credit' => 0,
             'line_description' => 'إيداع شيك — ' . $check->partyName()],
            ['account_id' => $chrAcct->id,  'debit' => 0, 'credit' => $check->amount,
             'line_description' => 'شيكات تحت التحصيل — تحويل للبنك'],
        ]);

        $check->updateQuietly(['deposit_journal_entry_id' => $entry->id, 'status' => 'deposited']);
        return $entry;
    }

    /**
     * عند ارتداد الشيك (bounced)
     * إذا كان مودَعاً: Dr. ذمم العملاء / Cr. البنك
     * إذا كان تحت التحصيل: Dr. ذمم العملاء / Cr. شيكات تحت التحصيل
     */
    public function postBounced(Check $check): JournalEntry
    {
        $this->assertType($check, 'receivable');
        $this->assertStatus($check, ['received', 'deposited']);

        $arCode  = Setting::get('account_ar_code', '1200');
        $arAcct  = $this->resolveAccount($arCode, 'ذمم العملاء');

        if ($check->status === 'deposited') {
            $bankCode = Setting::get('account_bank_code', '1100');
            $crAcct   = $this->resolveAccount($bankCode, 'البنك');
            $desc     = 'عكس إيداع شيك مرتجع';
        } else {
            $chrCode = Setting::get('account_checks_receivable_code', '1120');
            $crAcct  = $this->resolveAccount($chrCode, 'شيكات تحت التحصيل');
            $desc    = 'شيك مرتجع — إعادة للذمم';
        }

        $entry = $this->buildEntry($check, 'شيك مرتجع — ' . $check->check_number, [
            ['account_id' => $arAcct->id, 'debit' => $check->amount, 'credit' => 0,
             'line_description' => 'ذمم العملاء — شيك مرتجع — ' . $check->partyName()],
            ['account_id' => $crAcct->id, 'debit' => 0, 'credit' => $check->amount,
             'line_description' => $desc],
        ]);

        $check->updateQuietly(['clearing_journal_entry_id' => $entry->id, 'status' => 'bounced']);
        return $entry;
    }

    /**
     * إعادة إيداع شيك مرتدّ (Re-present) — إعادة الشيك المرتجع لحافظة التحصيل.
     *   Dr. شيكات تحت التحصيل (1120) — يعود الشيك أصلاً
     *   Cr. ذمم العملاء (1200)        — يخرج الدَّيْن من الذمم مجدداً
     */
    public function postRepresented(Check $check): JournalEntry
    {
        $this->assertType($check, 'receivable');
        $this->assertStatus($check, ['bounced']);

        $chrCode = Setting::get('account_checks_receivable_code', '1120');
        $arCode  = Setting::get('account_ar_code', '1200');

        $chrAcct = $this->resolveAccount($chrCode, 'شيكات تحت التحصيل');
        $arAcct  = $this->resolveAccount($arCode,  'ذمم العملاء');

        $entry = $this->buildEntry($check, 'إعادة إيداع شيك مرتدّ — ' . $check->check_number, [
            ['account_id' => $chrAcct->id, 'debit' => $check->amount, 'credit' => 0,
             'line_description' => 'إعادة شيك للتحصيل — ' . $check->partyName()],
            ['account_id' => $arAcct->id,  'debit' => 0, 'credit' => $check->amount,
             'line_description' => 'ذمم العملاء — إعادة إيداع'],
        ]);

        $check->updateQuietly(['journal_entry_id' => $entry->id, 'status' => 'received']);
        return $entry;
    }

    /**
     * تجيير شيك وارد لمورد (Endorsement) — تظهير الشيك لسداد ذمة مورد بدل إيداعه.
     *   Dr. ذمم الموردين (2000)        — يقلّ ما ندين به للمورد
     *   Cr. شيكات تحت التحصيل (1120)   — الشيك يخرج من دفاترنا (انتقل للمورد)
     */
    public function postEndorsed(Check $check, int $supplierId): JournalEntry
    {
        $this->assertType($check, 'receivable');
        $this->assertStatus($check, ['received']);

        $chrCode = Setting::get('account_checks_receivable_code', '1120');
        $apCode  = Setting::get('account_ap_code', '2000');

        $chrAcct = $this->resolveAccount($chrCode, 'شيكات تحت التحصيل');
        $apAcct  = $this->resolveAccount($apCode,  'ذمم الموردين');

        $supplier = \App\Models\Supplier::find($supplierId);

        $entry = $this->buildEntry($check, 'تجيير شيك وارد لمورد — ' . $check->check_number, [
            ['account_id' => $apAcct->id, 'debit' => $check->amount, 'credit' => 0,
             'line_description' => 'سداد بشيك مُجيَّر — ' . ($supplier?->name ?? 'مورد')],
            ['account_id' => $chrAcct->id, 'debit' => 0, 'credit' => $check->amount,
             'line_description' => 'شيكات تحت التحصيل — تجيير'],
        ]);

        $check->updateQuietly([
            'clearing_journal_entry_id' => $entry->id,
            'endorsed_to_supplier_id'   => $supplierId,
            'status'                    => 'endorsed',
        ]);
        return $entry;
    }

    // ── Payable ────────────────────────────────────────────────────────────────

    /**
     * عند إصدار الشيك للمورد (pending)
     * Dr. ذمم الموردين / Cr. شيكات مستحقة الدفع
     */
    public function postPending(Check $check): JournalEntry
    {
        $this->assertType($check, 'payable');

        $chpCode = Setting::get('account_checks_payable_code', '2030');
        $apCode  = Setting::get('account_ap_code', '2000');

        $chpAcct = $this->resolveAccount($chpCode, 'شيكات مستحقة الدفع');
        $apAcct  = $this->resolveAccount($apCode,  'ذمم الموردين');

        $entry = $this->buildEntry($check, 'إصدار شيك صادر — ' . $check->check_number, [
            ['account_id' => $apAcct->id,  'debit' => $check->amount, 'credit' => 0,
             'line_description' => 'ذمم الموردين — ' . $check->partyName()],
            ['account_id' => $chpAcct->id, 'debit' => 0, 'credit' => $check->amount,
             'line_description' => 'شيكات مستحقة الدفع — ' . $check->partyName()],
        ]);

        $check->updateQuietly(['journal_entry_id' => $entry->id, 'status' => 'pending']);
        return $entry;
    }

    /**
     * عند مقاصة الشيك الصادر (cleared) — البنك يصرفه
     * Dr. شيكات مستحقة الدفع / Cr. البنك
     */
    public function postPayableCleared(Check $check): JournalEntry
    {
        $this->assertType($check, 'payable');
        $this->assertStatus($check, ['pending']);

        $chpCode  = Setting::get('account_checks_payable_code', '2030');
        $bankCode = Setting::get('account_bank_code', '1100');

        $chpAcct  = $this->resolveAccount($chpCode,  'شيكات مستحقة الدفع');
        $bankAcct = $this->resolveAccount($bankCode,  'البنك');

        $entry = $this->buildEntry($check, 'صرف شيك من البنك — ' . $check->check_number, [
            ['account_id' => $chpAcct->id,  'debit' => $check->amount, 'credit' => 0,
             'line_description' => 'شيكات مستحقة الدفع — تسوية'],
            ['account_id' => $bankAcct->id, 'debit' => 0, 'credit' => $check->amount,
             'line_description' => 'البنك — صرف شيك — ' . $check->partyName()],
        ]);

        $check->updateQuietly(['clearing_journal_entry_id' => $entry->id, 'status' => 'cleared']);
        return $entry;
    }

    /**
     * عند إعادة الشيك الصادر للمورد (returned)
     * Dr. شيكات مستحقة الدفع / Cr. ذمم الموردين
     */
    public function postReturned(Check $check): JournalEntry
    {
        $this->assertType($check, 'payable');
        $this->assertStatus($check, ['pending']);

        $chpCode = Setting::get('account_checks_payable_code', '2030');
        $apCode  = Setting::get('account_ap_code', '2000');

        $chpAcct = $this->resolveAccount($chpCode, 'شيكات مستحقة الدفع');
        $apAcct  = $this->resolveAccount($apCode,  'ذمم الموردين');

        $entry = $this->buildEntry($check, 'إعادة شيك للمورد — ' . $check->check_number, [
            ['account_id' => $chpAcct->id, 'debit' => $check->amount, 'credit' => 0,
             'line_description' => 'شيكات مستحقة الدفع — عكس'],
            ['account_id' => $apAcct->id,  'debit' => 0, 'credit' => $check->amount,
             'line_description' => 'ذمم الموردين — إعادة شيك — ' . $check->partyName()],
        ]);

        $check->updateQuietly(['clearing_journal_entry_id' => $entry->id, 'status' => 'returned']);
        return $entry;
    }

    // ── Internals ──────────────────────────────────────────────────────────────

    private function buildEntry(Check $check, string $description, array $lines): JournalEntry
    {
        $branchId = $check->branch_id
            ?? auth()->user()?->branch_id
            ?? (int) Setting::get('default_branch_id', 0) ?: null;

        return $this->ledger->buildEntryPublic([
            'entry_date'  => now()->toDateString(),
            'description' => $description,
            'source_type' => 'check',
            'source_id'   => $check->id,
            'branch_id'   => $branchId,
        ], $lines);
    }

    private function resolveAccount(string $code, string $label)
    {
        $account = \App\Models\Account::where('code', $code)->where('is_active', true)->first();

        if (!$account) {
            throw new RuntimeException("الحساب [{$code}] ({$label}) غير موجود — أضفه من دليل الحسابات أو راجع الإعدادات");
        }
        if ($account->is_header) {
            throw new RuntimeException("الحساب [{$code}] ({$label}) حساب رئيسي — لا يمكن الترحيل إليه");
        }
        return $account;
    }

    private function assertType(Check $check, string $expected): void
    {
        if ($check->type !== $expected) {
            throw new RuntimeException("هذه العملية مخصصة لشيكات {$expected} فقط");
        }
    }

    private function assertStatus(Check $check, array $allowed): void
    {
        if (!in_array($check->status, $allowed)) {
            throw new RuntimeException(
                "لا يمكن تنفيذ هذه العملية — حالة الشيك الحالية: {$check->statusLabel()}"
            );
        }
    }
}
