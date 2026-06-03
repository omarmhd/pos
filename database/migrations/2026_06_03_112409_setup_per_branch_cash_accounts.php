<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-Branch Cash & Bank Accounts
 *
 * Architecture:
 *   1000 (صندوق/نقدية) → Header account (summary)
 *     ├── 1000.01  صندوق المقر الرئيسي  (main branch leaf)
 *     └── 1000.XX  صندوق [branch name]  (auto-created per branch)
 *
 *   1100 (البنوك) → Header account (summary)
 *     ├── 1100.01  بنك المقر الرئيسي
 *     └── 1100.XX  بنك [branch name]
 *
 * Why headers? Balance Sheet shows 1000 as the total cash across all branches.
 * Each branch can be queried individually via its leaf account.
 *
 * Backward compat: existing 1000/1100 transactions are preserved.
 * We rename them to 1000.00/1100.00 (head office fallback) and make
 * original codes the headers — no data loss.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Rename existing leaf accounts to .00 sub-codes ─────────────────
        // This preserves all existing journal entry lines
        $now = now();

        // Get current IDs
        $cash1000 = DB::table('accounts')->where('code', '1000')->first();
        $bank1100 = DB::table('accounts')->where('code', '1100')->first();

        if ($cash1000) {
            // Create sub-account 1000.00 that absorbs existing 1000 transactions
            DB::table('accounts')->insertOrIgnore([
                'code'       => '1000.00',
                'name'       => 'صندوق عام / افتراضي',
                'type'       => 'asset',
                'sub_type'   => 'current_asset',
                'is_header'  => false,
                'is_active'  => true,
                'parent_id'  => $cash1000->id,
                'notes'      => 'الحساب الافتراضي — يُستخدم قبل تعيين صناديق الفروع',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Make 1000 a header
            DB::table('accounts')->where('code', '1000')
                ->update(['is_header' => true, 'updated_at' => $now]);
        }

        if ($bank1100) {
            DB::table('accounts')->insertOrIgnore([
                'code'       => '1100.00',
                'name'       => 'بنك عام / افتراضي',
                'type'       => 'asset',
                'sub_type'   => 'current_asset',
                'is_header'  => false,
                'is_active'  => true,
                'parent_id'  => $bank1100->id,
                'notes'      => 'الحساب الافتراضي — يُستخدم قبل تعيين بنوك الفروع',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('accounts')->where('code', '1100')
                ->update(['is_header' => true, 'updated_at' => $now]);
        }

        // ── 2. Add cash_account_id + bank_account_id to branches ──────────────
        if (!Schema::hasColumn('branches', 'cash_account_id')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->foreignId('cash_account_id')->nullable()
                      ->after('is_active')->constrained('accounts')->nullOnDelete();
                $table->foreignId('bank_account_id')->nullable()
                      ->after('cash_account_id')->constrained('accounts')->nullOnDelete();
            });
        }

        // ── 3. Create cash/bank accounts for existing branches ────────────────
        $branches = DB::table('branches')->orderBy('id')->get();
        $cash1000Fresh = DB::table('accounts')->where('code', '1000')->first();
        $bank1100Fresh = DB::table('accounts')->where('code', '1100')->first();

        foreach ($branches as $branch) {
            $seq = str_pad((string) $branch->id, 2, '0', STR_PAD_LEFT);

            // Cash account
            $cashCode = '1000.' . $seq;
            $cashId   = DB::table('accounts')
                ->where('code', $cashCode)->value('id');

            if (!$cashId) {
                $cashId = DB::table('accounts')->insertGetId([
                    'code'       => $cashCode,
                    'name'       => 'صندوق — ' . $branch->name,
                    'type'       => 'asset',
                    'sub_type'   => 'current_asset',
                    'is_header'  => false,
                    'is_active'  => true,
                    'parent_id'  => $cash1000Fresh?->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // Bank account
            $bankCode = '1100.' . $seq;
            $bankId   = DB::table('accounts')
                ->where('code', $bankCode)->value('id');

            if (!$bankId) {
                $bankId = DB::table('accounts')->insertGetId([
                    'code'       => $bankCode,
                    'name'       => 'بنك — ' . $branch->name,
                    'type'       => 'asset',
                    'sub_type'   => 'current_asset',
                    'is_header'  => false,
                    'is_active'  => true,
                    'parent_id'  => $bank1100Fresh?->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('branches')->where('id', $branch->id)->update([
                'cash_account_id' => $cashId,
                'bank_account_id' => $bankId,
                'updated_at'      => $now,
            ]);
        }

        // ── 4. Update settings to point to the generic fallback accounts ──────
        $fallbackCash = DB::table('accounts')->where('code', '1000.00')->value('id');
        $fallbackBank = DB::table('accounts')->where('code', '1100.00')->value('id');

        if ($fallbackCash) {
            DB::table('settings')->updateOrInsert(
                ['key' => 'account_cash_fallback_id'],
                ['value' => $fallbackCash, 'created_at' => $now, 'updated_at' => $now]
            );
        }
        if ($fallbackBank) {
            DB::table('settings')->updateOrInsert(
                ['key' => 'account_bank_fallback_id'],
                ['value' => $fallbackBank, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        // Remove cash/bank FKs from branches
        if (Schema::hasColumn('branches', 'cash_account_id')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->dropForeign(['cash_account_id']);
                $table->dropForeign(['bank_account_id']);
                $table->dropColumn(['cash_account_id', 'bank_account_id']);
            });
        }

        // Restore 1000 and 1100 as leaf accounts
        DB::table('accounts')->whereIn('code', ['1000', '1100'])
            ->update(['is_header' => false]);

        // Remove sub-accounts we created (careful — only delete if no JE lines)
        DB::table('accounts')
            ->whereRaw("code REGEXP '^(1000|1100)\\\\.[0-9]+$'")
            ->whereNotIn('id', function ($q) {
                $q->select('account_id')->from('journal_entry_lines');
            })
            ->delete();

        DB::table('settings')->whereIn('key', ['account_cash_fallback_id', 'account_bank_fallback_id'])->delete();
    }
};
