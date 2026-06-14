<?php

namespace App\Observers;

use App\Models\Sale;

/**
 * ⚠️ مهجور (DEPRECATED) — لا تُسجِّل هذا المراقب.
 *
 * كان هذا الكلاس يحتوي تطبيقاً ثانياً ومتباعداً لترحيل قيد المبيعات
 * (بلا فصل ضريبة، بلا معالجة بونص/إيداع/شيكات، وبلا فحص توازن أو قفل فترة).
 *
 * مسار الترحيل المعتمد والوحيد هو: App\Services\LedgerPostingService::postSale()
 * الذي يفرض توازن القيد وقفل الفترات المحاسبية.
 *
 * أُفرِغ هذا المراقب من منطق الترحيل لإزالة خطر الازدواج/الترحيل الخاطئ (تدقيق: C-2).
 * يبقى الكلاس كـ no-op آمن فقط لتفادي كسر أي تسجيل تاريخي محتمل؛ ويُفضَّل حذفه نهائياً.
 *
 * @deprecated استخدم LedgerPostingService::postSale() بدلاً من هذا المراقب.
 */
class SaleObserver
{
    /** no-op مقصود — الترحيل يتم حصراً عبر LedgerPostingService::postSale(). */
    public function created(Sale $sale): void
    {
        // intentionally left blank — posting is centralized in LedgerPostingService.
    }
}
