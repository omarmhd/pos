<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Branch;
use Illuminate\Support\Facades\DB;

/**
 * Handles automatic GL account setup for branches.
 *
 * When a branch is created:
 *   1000.XX  — صندوق [branch name]  (cash account for this branch)
 *   1100.XX  — بنك [branch name]    (bank account for this branch)
 *
 * The XX suffix = branch.id zero-padded to 2 digits.
 * Example: Branch id=3 → 1000.03, 1100.03
 */
class BranchAccountingService
{
    /**
     * Called from Branch::boot() static::created().
     * Creates dedicated cash, bank, due-from, and due-to accounts.
     *
     *  1000.XX — صندوق الفرع      (cash)
     *  1100.XX — بنك الفرع        (bank)
     *  1700.XX — مستحق من الفرع   (Due From — asset)
     *  2700.XX — مستحق للفرع      (Due To — liability)
     */
    public static function setupAccounts(Branch $branch): void
    {
        $seq = str_pad((string) $branch->id, 2, '0', STR_PAD_LEFT);

        // Parent accounts
        $cashParent = Account::where('code', '1000')->first();
        $bankParent = Account::where('code', '1100')->first();

        // ── Cash account ──────────────────────────────────────────────────────
        $cashCode    = '1000.' . $seq;
        $cashAccount = Account::firstOrCreate(
            ['code' => $cashCode],
            [
                'name'      => 'صندوق — ' . $branch->name,
                'type'      => 'asset',
                'sub_type'  => 'current_asset',
                'is_header' => false,
                'is_active' => true,
                'parent_id' => $cashParent?->id,
            ]
        );

        // ── Bank account ──────────────────────────────────────────────────────
        $bankCode    = '1100.' . $seq;
        $bankAccount = Account::firstOrCreate(
            ['code' => $bankCode],
            [
                'name'      => 'بنك — ' . $branch->name,
                'type'      => 'asset',
                'sub_type'  => 'current_asset',
                'is_header' => false,
                'is_active' => true,
                'parent_id' => $bankParent?->id,
            ]
        );

        // ── Due From account (asset — مستحق من الفرع) ──────────────────────
        $dueFromCode = '1700.' . $seq;
        $dueFromParent = Account::where('code', '1700')->first();
        Account::firstOrCreate(
            ['code' => $dueFromCode],
            [
                'name'      => 'مستحق من — ' . $branch->name,
                'type'      => 'asset',
                'sub_type'  => 'current_asset',
                'is_header' => false,
                'is_active' => true,
                'parent_id' => $dueFromParent?->id,
            ]
        );

        // ── Due To account (liability — مستحق للفرع) ───────────────────────
        $dueToCode = '2700.' . $seq;
        $dueToParent = Account::where('code', '2700')->first();
        Account::firstOrCreate(
            ['code' => $dueToCode],
            [
                'name'      => 'مستحق لـ — ' . $branch->name,
                'type'      => 'liability',
                'sub_type'  => 'current_liability',
                'is_header' => false,
                'is_active' => true,
                'parent_id' => $dueToParent?->id,
            ]
        );

        // Link to branch (use updateQuietly to avoid triggering events again)
        $branch->updateQuietly([
            'cash_account_id' => $cashAccount->id,
            'bank_account_id' => $bankAccount->id,
        ]);
    }

    /**
     * Resolve the "Due From Branch X" account ID (asset — 1700.XX).
     * Used when Branch A sends funds to Branch B: Branch A debits this account.
     */
    public static function dueFromAccountId(int $branchId): int
    {
        $seq  = str_pad((string) $branchId, 2, '0', STR_PAD_LEFT);
        $code = '1700.' . $seq;
        $id   = Account::where('code', $code)->where('is_active', true)->value('id');
        if (!$id) {
            throw new \RuntimeException("حساب مستحق من الفرع [{$code}] غير موجود — راجع إعدادات الفروع");
        }
        return (int) $id;
    }

    /**
     * Resolve the "Due To Branch X" account ID (liability — 2700.XX).
     * Used by Branch B when receiving funds from Branch A: Branch B credits this account.
     */
    public static function dueToAccountId(int $branchId): int
    {
        $seq  = str_pad((string) $branchId, 2, '0', STR_PAD_LEFT);
        $code = '2700.' . $seq;
        $id   = Account::where('code', $code)->where('is_active', true)->value('id');
        if (!$id) {
            throw new \RuntimeException("حساب مستحق للفرع [{$code}] غير موجود — راجع إعدادات الفروع");
        }
        return (int) $id;
    }

    /**
     * Resolve the correct cash account ID for a given branch.
     *
     * Priority:
     *   1. branch.cash_account_id (specific account for this branch)
     *   2. System default cash code (account_cash_code setting → 1000.00)
     *   3. Fallback to first active leaf account under 1000
     *
     * @return int  Always returns a valid account ID
     */
    public static function cashAccountId(?int $branchId): int
    {
        if ($branchId) {
            $id = DB::table('branches')
                ->where('id', $branchId)
                ->value('cash_account_id');
            if ($id) {
                return (int) $id;
            }
        }

        return static::fallbackCashId();
    }

    /**
     * Resolve the correct bank account ID for a given branch.
     */
    public static function bankAccountId(?int $branchId): int
    {
        if ($branchId) {
            $id = DB::table('branches')
                ->where('id', $branchId)
                ->value('bank_account_id');
            if ($id) {
                return (int) $id;
            }
        }

        return static::fallbackBankId();
    }

    /**
     * Resolve cash or bank account ID based on payment method.
     * Used by LedgerPostingService.
     */
    public static function cashOrBankId(string $paymentMethod, ?int $branchId): int
    {
        return match($paymentMethod) {
            'card', 'bank' => static::bankAccountId($branchId),
            default        => static::cashAccountId($branchId),
        };
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private static function fallbackCashId(): int
    {
        // Try 1000.00 (generic sub-account) first — leaf accounts only
        $id = Account::where('code', '1000.00')
            ->where('is_active', true)
            ->where('is_header', false)
            ->value('id');
        if ($id) return (int) $id;

        // Fallback to 1000 — ONLY if it is a leaf account (not header).
        // If 1000 is a header account, posting to it would exclude it from
        // the balance sheet (getBalanceSheet filters out is_header=true).
        $id = Account::where('code', '1000')
            ->where('is_active', true)
            ->where('is_header', false)
            ->value('id');
        if ($id) return (int) $id;

        throw new \RuntimeException('لا يوجد حساب صندوق مُفعَّل (غير رئيسي) في النظام — تحقق من شجرة الحسابات');
    }

    private static function fallbackBankId(): int
    {
        $id = Account::where('code', '1100.00')
            ->where('is_active', true)
            ->where('is_header', false)
            ->value('id');
        if ($id) return (int) $id;

        $id = Account::where('code', '1100')
            ->where('is_active', true)
            ->where('is_header', false)
            ->value('id');
        if ($id) return (int) $id;

        throw new \RuntimeException('لا يوجد حساب بنك مُفعَّل (غير رئيسي) في النظام — تحقق من شجرة الحسابات');
    }
}
