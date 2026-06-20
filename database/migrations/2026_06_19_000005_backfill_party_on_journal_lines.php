<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * وسم القيود التاريخية بالطرف (Backfill) — لمرة واحدة.
 *
 * يَسِم سطور حسابات المراقبة (ذمم العملاء/الموردين/إيداعات العملاء) في القيود
 * المولّدة تلقائيًا بالطرف المأخوذ من المستند المصدر، حتى يكتمل كشف الأستاذ
 * المساعد لكل عميل/مورّد بأرصدته السابقة (الفواتير + المدفوعات).
 *
 * يقتصر التحديث على السطور غير الموسومة (party_type IS NULL) وعلى حسابات
 * المراقبة فقط — فلا يمسّ سطور الإيراد/المصروف/النقدية.
 */
return new class extends Migration
{
    public function up(): void
    {
        $accId = fn(string $code) => optional(
            DB::table('accounts')->where('code', $code)->first()
        )->id;

        $arId  = $accId(Setting::get('account_ar_code', '1200'));
        $apId  = $accId(Setting::get('account_ap_code', '2000'));
        $depId = $accId(Setting::get('account_customer_deposits_code', '2050'));

        // [source_type, source_table, fk, party_class, control_account_ids]
        $maps = [
            [\App\Models\Sale::class,            'sales',             'customer_id', \App\Models\Customer::class, array_filter([$arId, $depId])],
            [\App\Models\CustomerPayment::class, 'customer_payments', 'customer_id', \App\Models\Customer::class, array_filter([$arId])],
            [\App\Models\Purchase::class,        'purchases',         'supplier_id', \App\Models\Supplier::class, array_filter([$apId])],
            [\App\Models\ExpenseInvoice::class,  'expense_invoices',  'supplier_id', \App\Models\Supplier::class, array_filter([$apId])],
            [\App\Models\SupplierPayment::class, 'supplier_payments', 'supplier_id', \App\Models\Supplier::class, array_filter([$apId])],
        ];

        foreach ($maps as [$sourceType, $table, $fk, $partyClass, $accountIds]) {
            if (empty($accountIds)) {
                continue;
            }

            DB::table('journal_entry_lines as jel')
                ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
                ->join("$table as src", 'src.id', '=', 'je.source_id')
                ->where('je.source_type', $sourceType)
                ->whereNotNull("src.$fk")
                ->whereIn('jel.account_id', $accountIds)
                ->whereNull('jel.party_type')
                ->update([
                    'jel.party_type' => $partyClass,
                    'jel.party_id'   => DB::raw("src.$fk"),
                ]);
        }
    }

    public function down(): void
    {
        // ترحيل بيانات لمرة واحدة — لا تراجع (حتى لا تُمسح وسوم القيود الجديدة).
    }
};
