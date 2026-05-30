<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Posts all unposted purchases and sales to the General Ledger.
 *
 * WHY THIS EXISTS:
 *   DemoDataSeeder inserts purchases/sales via DB::table() (raw SQL),
 *   bypassing the LedgerPostingService. This migration runs after the seeder
 *   and creates the missing journal_entry_lines so the balance sheet is correct.
 *
 * Also fixes any existing data where is_posted=1 but no GL entry exists
 * (caused by the earlier seeder bug).
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // ── Resolve account IDs once ──────────────────────────────────────
        $acct = fn(string $code) => DB::table('accounts')->where('code', $code)->value('id');

        $cashId      = $acct(DB::table('settings')->where('key','account_cash_code')     ->value('value') ?? '1000');
        $apId        = $acct(DB::table('settings')->where('key','account_ap_code')        ->value('value') ?? '2000');
        $invId       = $acct(DB::table('settings')->where('key','account_inventory_code') ->value('value') ?? '1300');
        $cogsId      = $acct(DB::table('settings')->where('key','account_cogs_code')      ->value('value') ?? '5000');
        $salesId     = $acct(DB::table('settings')->where('key','account_sales_code')     ->value('value') ?? '4000');
        $arId        = $acct(DB::table('settings')->where('key','account_ar_code')        ->value('value') ?? '1200');
        $discountId  = $acct(DB::table('settings')->where('key','account_discount_code')  ->value('value') ?? '4300');

        if (!$invId || !$apId || !$cashId || !$cogsId || !$salesId || !$arId) {
            // Accounts not seeded yet — skip (safe to run before DatabaseSeeder)
            return;
        }

        $adminId = DB::table('users')->where('email','admin@demo.com')->value('id') ?? 1;

        // ── 1. Post purchases without GL entries ──────────────────────────
        $postedPurchaseSources = DB::table('journal_entries')
            ->where('source_type', 'App\Models\Purchase')
            ->whereNotNull('source_id')
            ->pluck('source_id');

        $purchases = DB::table('purchases')
            ->whereNotIn('id', $postedPurchaseSources)
            ->orderBy('created_at')
            ->get(['id','invoice_number','supplier_id','total_amount','paid_amount','created_at']);

        $jeSeq = DB::table('journal_entries')
            ->whereDate('created_at', now()->toDateString())
            ->count();

        foreach ($purchases as $p) {
            $total  = round((float)$p->total_amount, 2);
            $paid   = round((float)$p->paid_amount,  2);
            $unpaid = round($total - $paid, 2);

            if ($total <= 0) continue;

            $jeSeq++;
            $entryNumber = 'JE-' . now()->format('Ymd') . '-' . str_pad($jeSeq, 6, '0', STR_PAD_LEFT);

            $jeId = DB::table('journal_entries')->insertGetId([
                'entry_number' => $entryNumber,
                'entry_date'   => substr($p->created_at, 0, 10),
                'description'  => 'قيد مشتريات – ' . $p->invoice_number,
                'reference'    => $p->invoice_number,
                'source_type'  => 'App\Models\Purchase',
                'source_id'    => $p->id,
                'user_id'      => $adminId,
                'posted_at'    => $p->created_at,
                'created_at'   => $p->created_at,
                'updated_at'   => $p->created_at,
            ]);

            // DR Inventory (full amount)
            DB::table('journal_entry_lines')->insert([
                'journal_entry_id' => $jeId,
                'account_id'       => $invId,
                'debit'            => $total,
                'credit'           => 0,
                'line_description' => 'مشتريات بضاعة – ' . $p->invoice_number,
                'created_at'       => $p->created_at,
                'updated_at'       => $p->created_at,
            ]);

            // CR AP (unpaid portion)
            if ($unpaid > 0.001) {
                DB::table('journal_entry_lines')->insert([
                    'journal_entry_id' => $jeId,
                    'account_id'       => $apId,
                    'debit'            => 0,
                    'credit'           => $unpaid,
                    'line_description' => 'ذمة مورد',
                    'created_at'       => $p->created_at,
                    'updated_at'       => $p->created_at,
                ]);
            }

            // CR Cash (paid portion)
            if ($paid > 0.001) {
                DB::table('journal_entry_lines')->insert([
                    'journal_entry_id' => $jeId,
                    'account_id'       => $cashId,
                    'debit'            => 0,
                    'credit'           => $paid,
                    'line_description' => 'دفعة فورية عند الشراء',
                    'created_at'       => $p->created_at,
                    'updated_at'       => $p->created_at,
                ]);
            }

            DB::table('purchases')->where('id', $p->id)->update(['is_posted' => 1]);
        }

        // ── 2. Post sales without GL entries ─────────────────────────────
        $postedSaleSources = DB::table('journal_entries')
            ->where('source_type', 'App\Models\Sale')
            ->whereNotNull('source_id')
            ->pluck('source_id');

        $sales = DB::table('sales')
            ->whereNotIn('id', $postedSaleSources)
            ->orderBy('created_at')
            ->get(['id','invoice_number','customer_id','is_credit','subtotal',
                   'discount','tax','total_amount','paid_amount','payment_method',
                   'created_at']);

        foreach ($sales as $s) {
            $subtotal = round((float)$s->subtotal,      2);
            $discount = round((float)$s->discount,      2);
            $tax      = round((float)$s->tax,           2);
            $total    = round((float)$s->total_amount,  2);
            $paid     = round((float)$s->paid_amount,   2);

            if ($total <= 0) continue;

            // Compute COGS for this sale
            $cogs = round((float) DB::table('sale_items')
                ->where('sale_id', $s->id)
                ->sum(DB::raw('quantity * cost_price')), 2);

            $jeSeq++;
            $entryNumber = 'JE-' . now()->format('Ymd') . '-' . str_pad($jeSeq, 6, '0', STR_PAD_LEFT);

            $jeId = DB::table('journal_entries')->insertGetId([
                'entry_number' => $entryNumber,
                'entry_date'   => substr($s->created_at, 0, 10),
                'description'  => 'قيد مبيعات – ' . $s->invoice_number,
                'reference'    => $s->invoice_number,
                'source_type'  => 'App\Models\Sale',
                'source_id'    => $s->id,
                'user_id'      => $adminId,
                'posted_at'    => $s->created_at,
                'created_at'   => $s->created_at,
                'updated_at'   => $s->created_at,
            ]);

            $lines = [];

            // DR: Cash/AR (debit side = total received or owed)
            $debitAccountId = ($s->is_credit || $s->customer_id) ? $arId : $cashId;
            $lines[] = [
                'journal_entry_id' => $jeId, 'account_id' => $debitAccountId,
                'debit' => $total, 'credit' => 0,
                'line_description' => $s->is_credit ? 'ذمة عميل' : 'نقدية مبيعات',
                'created_at' => $s->created_at, 'updated_at' => $s->created_at,
            ];

            // CR: Sales Revenue (subtotal before discount)
            $lines[] = [
                'journal_entry_id' => $jeId, 'account_id' => $salesId,
                'debit' => 0, 'credit' => $subtotal,
                'line_description' => 'إيراد مبيعات',
                'created_at' => $s->created_at, 'updated_at' => $s->created_at,
            ];

            // DR: Discount given (if any)
            if ($discount > 0.001 && $discountId) {
                $lines[] = [
                    'journal_entry_id' => $jeId, 'account_id' => $discountId,
                    'debit' => $discount, 'credit' => 0,
                    'line_description' => 'خصم ممنوح',
                    'created_at' => $s->created_at, 'updated_at' => $s->created_at,
                ];
            }

            // DR: COGS  /  CR: Inventory
            if ($cogs > 0.001) {
                $lines[] = [
                    'journal_entry_id' => $jeId, 'account_id' => $cogsId,
                    'debit' => $cogs, 'credit' => 0,
                    'line_description' => 'تكلفة البضاعة المباعة',
                    'created_at' => $s->created_at, 'updated_at' => $s->created_at,
                ];
                $lines[] = [
                    'journal_entry_id' => $jeId, 'account_id' => $invId,
                    'debit' => 0, 'credit' => $cogs,
                    'line_description' => 'إخراج بضاعة مباعة من المخزون',
                    'created_at' => $s->created_at, 'updated_at' => $s->created_at,
                ];
            }

            foreach (array_chunk($lines, 50) as $chunk) {
                DB::table('journal_entry_lines')->insert($chunk);
            }

            DB::table('sales')->where('id', $s->id)->update(['is_posted' => 1]);
        }
    }

    public function down(): void
    {
        // Remove demo GL entries (source_type = Purchase or Sale, not manually created)
        $jeIds = DB::table('journal_entries')
            ->whereIn('source_type', ['App\Models\Purchase', 'App\Models\Sale'])
            ->pluck('id');

        DB::table('journal_entry_lines')->whereIn('journal_entry_id', $jeIds)->delete();
        DB::table('journal_entries')->whereIn('id', $jeIds)->delete();

        DB::table('purchases')->update(['is_posted' => 0]);
        DB::table('sales')->update(['is_posted' => 0]);
    }
};
