<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EosbProvision;
use App\Models\Setting;
use Carbon\Carbon;

/**
 * حاسبة مكافأة نهاية الخدمة (EOSB) — قابلة للتخصيص بالكامل.
 *
 * الطريقة (الأصحّ محاسبيًا — أسلوب المطلوب التراكمي / IAS 19):
 *   1) نحسب "المطلوب المستحق" حتى تاريخ معيّن = مجموع أيام الاستحقاق عبر الشرائح
 *      × الأجر اليومي (= الراتب الأساس ÷ أيام الشهر).
 *   2) المخصص الشهري = المطلوب المستحق حتى نهاية الشهر − ما رُحِّل سابقًا للموظف.
 *
 * بهذا يُعالَج تلقائيًا: تجاوز الموظف لشريحة أعلى (مثل بعد 5 سنوات)، وتغيّر الراتب.
 *
 * القاعدة العامة من الإعدادات، مع إمكانية تجاوزها لكل موظف (eosb_tiers / eosb_salary_base).
 */
class EosbCalculator
{
    private array $tiers;          // [['to_year'=>int|null, 'days_per_year'=>float], ...] مرتبة تصاعديًا
    private string $salaryBase;    // 'basic' | 'gross'
    private float  $daysInMonth;   // مقسوم الأجر اليومي (افتراضي 30)

    public function __construct(private Employee $employee)
    {
        // أساس الراتب: تجاوز الموظف ثم الإعداد العام ثم 'basic'
        $this->salaryBase = $employee->eosb_salary_base
            ?: Setting::get('eosb_salary_base', 'basic');

        $this->daysInMonth = (float) (Setting::get('eosb_days_in_month', 30) ?: 30);

        // الشرائح: تجاوز الموظف (إن وُجد) ثم العام ثم الافتراضي
        $override = is_array($employee->eosb_tiers) ? $employee->eosb_tiers : null;
        $this->tiers = self::normalizeTiers($override ?: self::globalTiers());
    }

    /** الشرائح العامة من الإعدادات (JSON) أو الافتراضي الخليجي. */
    public static function globalTiers(): array
    {
        $raw = Setting::get('eosb_tiers');
        $decoded = $raw ? json_decode($raw, true) : null;

        if (is_array($decoded) && !empty($decoded)) {
            return $decoded;
        }

        // افتراضي معقول: 21 يومًا/سنة لأول 5 سنوات ثم 30 يومًا/سنة (نمط خليجي)
        return [
            ['to_year' => 5,    'days_per_year' => 21],
            ['to_year' => null, 'days_per_year' => 30],
        ];
    }

    /** ترتيب الشرائح تصاعديًا مع جعل الشريحة المفتوحة (to_year=null) أخيرة. */
    private static function normalizeTiers(array $tiers): array
    {
        $clean = [];
        foreach ($tiers as $t) {
            $to   = $t['to_year'] ?? null;
            $to   = ($to === '' || $to === null) ? null : (int) $to;
            $days = (float) ($t['days_per_year'] ?? 0);
            if ($days <= 0) continue;
            $clean[] = ['to_year' => $to, 'days_per_year' => $days];
        }
        usort($clean, function ($a, $b) {
            $av = $a['to_year'] ?? PHP_INT_MAX;
            $bv = $b['to_year'] ?? PHP_INT_MAX;
            return $av <=> $bv;
        });
        // ضمان وجود شريحة مفتوحة في النهاية
        if (empty($clean)) {
            $clean[] = ['to_year' => null, 'days_per_year' => 30];
        } elseif (end($clean)['to_year'] !== null) {
            $clean[] = ['to_year' => null, 'days_per_year' => end($clean)['days_per_year']];
        }
        return $clean;
    }

    /** الراتب الأساس للحساب حسب الإعداد. */
    public function salaryBaseAmount(): float
    {
        return $this->salaryBase === 'gross'
            ? $this->employee->grossMonthlySalary()
            : (float) $this->employee->base_salary;
    }

    /** الأجر اليومي = الراتب الأساس ÷ أيام الشهر. */
    public function dailyWage(): float
    {
        return $this->daysInMonth > 0
            ? $this->salaryBaseAmount() / $this->daysInMonth
            : 0.0;
    }

    /** مجموع أيام الاستحقاق عبر الشرائح لعدد سنوات خدمة. */
    public function entitlementDays(float $serviceYears): float
    {
        if ($serviceYears <= 0) return 0.0;

        $days = 0.0;
        $prev = 0.0;
        foreach ($this->tiers as $t) {
            $top      = $t['to_year'] === null ? $serviceYears : min($serviceYears, (float) $t['to_year']);
            $inBand   = max(0.0, $top - $prev);
            $days    += $inBand * (float) $t['days_per_year'];
            $prev     = $t['to_year'] === null ? $serviceYears : (float) $t['to_year'];
            if ($serviceYears <= $prev) break;
        }
        return $days;
    }

    /** عدد شهور الخدمة حتى تاريخ (مع مراعاة تاريخ الانتهاء إن سبق التاريخ). */
    public function serviceMonths(Carbon $asOf): int
    {
        if (!$this->employee->hire_date) return 0;
        $end = $asOf;
        if ($this->employee->termination_date && $this->employee->termination_date->lt($asOf)) {
            $end = $this->employee->termination_date;
        }
        $hire = Carbon::parse($this->employee->hire_date);
        return $hire->gt($end) ? 0 : (int) $hire->diffInMonths($end);
    }

    /** المطلوب المستحق (إجمالي مكافأة نهاية الخدمة) حتى تاريخ. */
    public function earnedLiability(Carbon $asOf): float
    {
        $months = $this->serviceMonths($asOf);
        $years  = $months / 12;
        return round($this->entitlementDays($years) * $this->dailyWage(), 2);
    }

    /** مجموع ما رُحِّل سابقًا لهذا الموظف. */
    public function priorProvisioned(): float
    {
        return round((float) EosbProvision::where('employee_id', $this->employee->id)
            ->sum('provision_amount'), 2);
    }

    /** تفصيل كامل لشهر معيّن (للمعاينة والترحيل). */
    public function breakdown(int $year, int $month): array
    {
        $periodEnd = Carbon::create($year, $month, 1)->endOfMonth();
        $months    = $this->serviceMonths($periodEnd);
        $years     = $months / 12;
        $dailyWage = $this->dailyWage();
        $days      = $this->entitlementDays($years);
        $earned    = round($days * $dailyWage, 2);
        $prior     = $this->priorProvisioned();
        $provision = round(max(0.0, $earned - $prior), 2);

        return [
            'employee'         => $this->employee,
            'service_months'   => $months,
            'service_years'    => round($years, 4),
            'salary_base_kind' => $this->salaryBase,
            'salary_base'      => round($this->salaryBaseAmount(), 2),
            'base_salary'      => round($this->salaryBaseAmount(), 2), // اسم بديل للتوافق مع العرض/التخزين
            'daily_wage'       => round($dailyWage, 4),
            'entitlement_days' => round($days, 2),
            'earned_liability' => $earned,
            'prior_cumulative' => $prior,
            'provision'        => $provision,
            'cumulative'       => round($prior + $provision, 2),
        ];
    }
}
