<?php

namespace App\Observers;

use App\Models\Sale;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Account;
use App\Models\Setting;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class SaleObserver
{
    public function created(Sale $sale)
    {
        DB::transaction(function () use ($sale) {
            // prefer settings or product-level account mapping
            $revenueAccountCode = Setting::get('account_sales_code', '4000');
            $cogsAccountCode = Setting::get('account_cogs_code', '5000');
            $inventoryAccountCode = Setting::get('account_inventory_code', '1300');
            $cashAccountCode = Setting::get('account_cash_code', '1000');
            $bankAccountCode = Setting::get('account_bank_code', '1100');
            $arAccountCode = Setting::get('account_ar_code', '1200');

            $revenueAccount = Account::where('code', $revenueAccountCode)->first();
            $cogsAccount = Account::where('code', $cogsAccountCode)->first();
            $inventoryAccount = Account::where('code', $inventoryAccountCode)->first();
            $cashAccount = Account::where('code', $cashAccountCode)->first();
            $bankAccount = Account::where('code', $bankAccountCode)->first();
            $arAccount = Account::where('code', $arAccountCode)->first();

            $total = (float) $sale->total_amount;
            $paid = (float) $sale->paid_amount;
            $change = (float) $sale->change_amount;

            $cogs = 0.0;
            foreach ($sale->items as $item) {
                $product = $item->product;
                $lineCogs = 0.0;
                if ($product && $product->cost_price) {
                    $lineCogs = (float)$product->cost_price * (float)$item->quantity;
                    $cogs += $lineCogs;
                }

                // create stock movement out and decrement product quantity
                StockMovement::create([
                    'product_id' => $product->id,
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                    'quantity' => $item->quantity,
                    'cost' => $product->cost_price ?? 0,
                    'movement_type' => 'out',
                    'notes' => 'بيع فاتورة ' . ($sale->invoice_number ?? ''),
                ]);

                // decrement product quantity safely
                if ($product) {
                    $product->decrement('quantity', $item->quantity);
                }
            }

            $entry = JournalEntry::create([
                'entry_date' => $sale->created_at ?? now(),
                'reference' => $sale->invoice_number ?? null,
                'source_type' => Sale::class,
                'source_id' => $sale->id,
                'description' => 'ترحيل قيد مبيعات لفاتورة ' . ($sale->invoice_number ?? ''),
                'user_id' => $sale->user_id ?? null,
                'posted_at' => now(),
            ]);

            // mark sale as posted
            $sale->is_posted = true;
            $sale->save();

            // audit log for posting
            \App\Models\AuditLog::create([
                'user_id' => $sale->user_id ?? auth()->id(),
                'auditable_type' => Sale::class,
                'auditable_id' => $sale->id,
                'action' => 'posted',
                'old_values' => null,
                'new_values' => ['is_posted' => true],
                'ip_address' => request()->ip() ?? null,
            ]);

            $lines = [];

            // Debit: Cash/Bank/AR depending on payment
            $receivable = max(0, $total - $paid);
            if ($paid > 0) {
                // decide account by payment_method
                $payAcc = $cashAccount;
                if ($sale->payment_method === 'card') $payAcc = $bankAccount ?? $cashAccount;
                if ($payAcc) {
                    $lines[] = [
                        'journal_entry_id' => $entry->id,
                        'account_id' => $payAcc->id,
                        'debit' => round($paid - $change, 2),
                        'credit' => 0,
                        'line_description' => 'استلام نقدي/بطاقة من فاتورة ' . ($sale->invoice_number ?? ''),
                    ];
                }
            }

            if ($receivable > 0) {
                if ($arAccount) {
                    $lines[] = [
                        'journal_entry_id' => $entry->id,
                        'account_id' => $arAccount->id,
                        'debit' => round($receivable, 2),
                        'credit' => 0,
                        'line_description' => 'باقي مستحق من فاتورة ' . ($sale->invoice_number ?? ''),
                    ];
                }
            }

            // Credit: Revenue (sales)
            if ($revenueAccount) {
                $lines[] = [
                    'journal_entry_id' => $entry->id,
                    'account_id' => $revenueAccount->id,
                    'debit' => 0,
                    'credit' => round($total, 2),
                    'line_description' => 'إيراد مبيعات فاتورة ' . ($sale->invoice_number ?? ''),
                ];
            }

            // Record COGS and Inventory reduction
            if ($cogs > 0) {
                if ($cogsAccount) {
                    $lines[] = [
                        'journal_entry_id' => $entry->id,
                        'account_id' => $cogsAccount->id,
                        'debit' => round($cogs, 2),
                        'credit' => 0,
                        'line_description' => 'تكلفة البضاعة المباعة فاتورة ' . ($sale->invoice_number ?? ''),
                    ];
                }

                // use inventory account from product if available else system inventory account
                $invAcc = $inventoryAccount;
                // if per-product inventory account exists, try to use it later when creating lines per product
                if ($inventoryAccount) {
                    $lines[] = [
                        'journal_entry_id' => $entry->id,
                        'account_id' => $inventoryAccount->id,
                        'debit' => 0,
                        'credit' => round($cogs, 2),
                        'line_description' => 'نقص مخزون عن فاتورة ' . ($sale->invoice_number ?? ''),
                    ];
                }
            }

            // create lines
            foreach ($lines as $ln) {
                JournalEntryLine::create($ln);
            }
        });
    }
}
