<?php

namespace App\Observers;

use App\Models\Purchase;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Account;
use App\Models\Setting;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class PurchaseObserver
{
    public function created(Purchase $purchase)
    {
        DB::transaction(function () use ($purchase) {
            $inventoryAccountCode = Setting::get('account_inventory_code', '1300');
            $suppliersAccountCode = Setting::get('account_ap_code', '2000');
            $cashAccountCode = Setting::get('account_cash_code', '1000');
            $bankAccountCode = Setting::get('account_bank_code', '1100');

            $inventoryAccount = Account::where('code', $inventoryAccountCode)->first();
            $suppliersAccount = Account::where('code', $suppliersAccountCode)->first();
            $cashAccount = Account::where('code', $cashAccountCode)->first();
            $bankAccount = Account::where('code', $bankAccountCode)->first();

            $total = (float) $purchase->total_amount;
            $paid = (float) $purchase->paid_amount;

            $entry = JournalEntry::create([
                'entry_date' => $purchase->created_at ?? now(),
                'reference' => $purchase->invoice_number ?? null,
                'source_type' => Purchase::class,
                'source_id' => $purchase->id,
                'description' => 'ترحيل قيد مشتريات لفاتورة ' . ($purchase->invoice_number ?? ''),
                'user_id' => $purchase->user_id ?? null,
                'posted_at' => now(),
            ]);

            $lines = [];

            // Debit: Inventory for full purchase total
            if ($inventoryAccount) {
                $lines[] = [
                    'journal_entry_id' => $entry->id,
                    'account_id' => $inventoryAccount->id,
                    'debit' => round($total, 2),
                    'credit' => 0,
                    'line_description' => 'زيادة مخزون عن فاتورة ' . ($purchase->invoice_number ?? ''),
                ];
            }

            // Credit: Cash/Bank for paid amount
            if ($paid > 0) {
                $payAcc = $cashAccount;
                // assume payment method not stored on purchases; if paid via bank, default to bank
                if ($payAcc) {
                    $lines[] = [
                        'journal_entry_id' => $entry->id,
                        'account_id' => $payAcc->id,
                        'debit' => 0,
                        'credit' => round($paid, 2),
                        'line_description' => 'دفع لمورد عن فاتورة ' . ($purchase->invoice_number ?? ''),
                    ];
                }
            }

            // Credit: Suppliers for remaining amount
            $remaining = max(0, $total - $paid);
            if ($remaining > 0 && $suppliersAccount) {
                $lines[] = [
                    'journal_entry_id' => $entry->id,
                    'account_id' => $suppliersAccount->id,
                    'debit' => 0,
                    'credit' => round($remaining, 2),
                    'line_description' => 'مطلوبات مورد عن فاتورة ' . ($purchase->invoice_number ?? ''),
                ];
            }

            foreach ($lines as $ln) {
                JournalEntryLine::create($ln);
            }

            // mark purchase as posted
            $purchase->is_posted = true;
            $purchase->save();

            \App\Models\AuditLog::create([
                'user_id' => $purchase->user_id ?? auth()->id(),
                'auditable_type' => Purchase::class,
                'auditable_id' => $purchase->id,
                'action' => 'posted',
                'old_values' => null,
                'new_values' => ['is_posted' => true],
                'ip_address' => request()->ip() ?? null,
            ]);

            // create stock movements and increase product quantity
            foreach ($purchase->items as $item) {
                $product = $item->product;
                if (!$product) continue;

                StockMovement::create([
                    'product_id' => $product->id,
                    'reference_type' => Purchase::class,
                    'reference_id' => $purchase->id,
                    'quantity' => $item->quantity,
                    'cost' => $item->unit_cost ?? ($product->cost_price ?? 0),
                    'movement_type' => 'in',
                    'notes' => 'وراد مشتريات فاتورة ' . ($purchase->invoice_number ?? ''),
                ]);

                $product->increment('quantity', $item->quantity);
            }
        });
    }
}
