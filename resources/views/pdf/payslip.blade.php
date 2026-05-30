<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 10px;
        color: #1a1a1a;
        direction: rtl;
    }

    .page { padding: 24px 28px; }

    /* ── Letterhead ── */
    .lh-table  { width: 100%; background: #2c3e50; color: white; padding: 14px 18px; margin-bottom: 3px; }
    .lh-right  { width: 60%; vertical-align: middle; }
    .lh-left   { width: 40%; vertical-align: middle; text-align: left; }
    .co-name   { font-size: 17px; font-weight: bold; }
    .co-sub    { font-size: 9px; margin-top: 2px; color: #ccc; }
    .doc-title { font-size: 18px; font-weight: bold; }
    .doc-sub   { font-size: 9px; margin-top: 3px; color: #ccc; }

    .divider { height: 3px; background: #2c3e50; margin-bottom: 14px; }

    /* ── Info boxes ── */
    .info-outer { width: 100%; margin-bottom: 14px; }
    .info-box   { width: 48%; vertical-align: top; border: 1px solid #dde4ed; padding: 9px 11px; }
    .info-gap   { width: 4%; }
    .box-title  { font-size: 8px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #eee; padding-bottom: 4px; margin-bottom: 6px; }
    .row-inner  { width: 100%; margin-bottom: 3px; }
    .row-key    { width: 50%; color: #666; vertical-align: top; }
    .row-val    { font-weight: bold; text-align: left; vertical-align: top; }

    /* ── Attendance ── */
    .att-outer  { width: 100%; margin-bottom: 14px; }
    .att-cell   { width: 23%; text-align: center; border: 1px solid #dde4ed; padding: 8px 4px; vertical-align: middle; }
    .att-gap    { width: 2.5%; }
    .att-num    { font-size: 17px; font-weight: bold; }
    .att-lbl    { font-size: 8px; color: #888; margin-top: 2px; }

    /* ── Two-col earnings/deductions ── */
    .two-col-outer { width: 100%; margin-bottom: 14px; }
    .col-half      { width: 48%; vertical-align: top; }
    .col-gap       { width: 4%; }
    .sec-title     { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; padding: 5px 9px; color: white; }
    .earn-title    { background: #27ae60; }
    .ded-title     { background: #e74c3c; }
    .detail-table  { width: 100%; border-collapse: collapse; border: 1px solid #dde4ed; border-top: none; }
    .detail-table td { padding: 5px 9px; border-bottom: 1px solid #eef1f5; font-size: 10px; }
    .detail-table tr:last-child td { border-bottom: none; }
    .d-label   { color: #444; text-align: right; }
    .d-amount  { text-align: left; font-weight: bold; }
    .d-sub td  { background: #f0f4f8; font-weight: bold; }
    .d-red     { color: #e74c3c; }
    .d-muted   { color: #aaa; text-align: center; }

    /* ── Net pay ── */
    .net-table  { width: 100%; background: #2c3e50; color: white; padding: 12px 18px; margin-bottom: 16px; }
    .net-label  { font-size: 13px; font-weight: bold; vertical-align: middle; }
    .net-amount { font-size: 20px; font-weight: bold; text-align: left; vertical-align: middle; }

    /* ── Footer / Signatures ── */
    .sig-table  { width: 100%; border-top: 1px solid #ddd; padding-top: 10px; margin-top: 8px; }
    .sig-cell   { width: 30%; text-align: center; font-size: 9px; color: #888; vertical-align: bottom; }
    .sig-mid    { vertical-align: bottom; text-align: center; font-size: 9px; color: #bbb; }
    .sig-line   { border-top: 1px solid #ccc; margin: 18px 8px 4px; }
</style>
</head>
<body>
<div class="page">

    {{-- ── Letterhead ── --}}
    <table class="lh-table">
        <tr>
            <td class="lh-right">
                <div class="co-name">{{ \App\Models\Setting::get('store_name', 'نظام POS للسوبر ماركت') }}</div>
                @if(\App\Models\Setting::get('company_address'))
                <div class="co-sub">{{ \App\Models\Setting::get('company_address') }}</div>
                @endif
                @if(\App\Models\Setting::get('company_phone'))
                <div class="co-sub">هاتف: {{ \App\Models\Setting::get('company_phone') }}</div>
                @endif
            </td>
            <td class="lh-left">
                <div class="doc-title">قسيمة راتب</div>
                <div class="doc-sub">{{ $item->payrollRun->periodLabel() }}</div>
                <div class="doc-sub">تاريخ الطباعة: {{ now()->format('Y/m/d H:i') }}</div>
            </td>
        </tr>
    </table>
    <div class="divider"></div>

    {{-- ── Employee & Payroll Info ── --}}
    <table class="info-outer">
        <tr>
            <td class="info-box">
                <div class="box-title">بيانات الموظف</div>
                <table class="row-inner"><tr>
                    <td class="row-key">الاسم:</td>
                    <td class="row-val">{{ $item->employee->name }}</td>
                </tr></table>
                <table class="row-inner"><tr>
                    <td class="row-key">رقم الموظف:</td>
                    <td class="row-val">{{ $item->employee->employee_code }}</td>
                </tr></table>
                <table class="row-inner"><tr>
                    <td class="row-key">المسمى الوظيفي:</td>
                    <td class="row-val">{{ $item->employee->job_title ?? '—' }}</td>
                </tr></table>
                <table class="row-inner"><tr>
                    <td class="row-key">القسم:</td>
                    <td class="row-val">{{ $item->employee->department ?? '—' }}</td>
                </tr></table>
            </td>
            <td class="info-gap"></td>
            <td class="info-box">
                <div class="box-title">بيانات المسير</div>
                <table class="row-inner"><tr>
                    <td class="row-key">المرجع:</td>
                    <td class="row-val">{{ $item->payrollRun->reference }}</td>
                </tr></table>
                <table class="row-inner"><tr>
                    <td class="row-key">الفترة:</td>
                    <td class="row-val">{{ $item->payrollRun->periodLabel() }}</td>
                </tr></table>
                <table class="row-inner"><tr>
                    <td class="row-key">تاريخ الصرف:</td>
                    <td class="row-val">{{ $item->payrollRun->pay_date->format('Y/m/d') }}</td>
                </tr></table>
                <table class="row-inner"><tr>
                    <td class="row-key">الحالة:</td>
                    <td class="row-val">{{ ['draft'=>'مسودة','approved'=>'معتمد','paid'=>'مصروف'][$item->payrollRun->status] ?? $item->payrollRun->status }}</td>
                </tr></table>
            </td>
        </tr>
    </table>

    {{-- ── Attendance ── --}}
    <table class="att-outer">
        <tr>
            <td class="att-cell">
                <div class="att-num" style="color:#27ae60;">{{ $item->days_worked }}</div>
                <div class="att-lbl">أيام الحضور</div>
            </td>
            <td class="att-gap"></td>
            <td class="att-cell">
                <div class="att-num" style="color:#e74c3c;">{{ $item->days_absent }}</div>
                <div class="att-lbl">أيام الغياب</div>
            </td>
            <td class="att-gap"></td>
            <td class="att-cell">
                <div class="att-num" style="color:#f39c12;">{{ number_format($item->overtime_hours, 1) }}</div>
                <div class="att-lbl">ساعات أوفرتايم</div>
            </td>
            <td class="att-gap"></td>
            <td class="att-cell">
                <div class="att-num" style="color:#3498db;">{{ $item->days_worked + $item->days_absent }}</div>
                <div class="att-lbl">إجمالي أيام الشهر</div>
            </td>
        </tr>
    </table>

    {{-- ── Earnings & Deductions ── --}}
    <table class="two-col-outer">
        <tr>
            <td class="col-half">
                <div class="sec-title earn-title">المستحقات</div>
                <table class="detail-table">
                    <tr><td class="d-label">الراتب الأساسي</td><td class="d-amount">{{ number_format($item->base_salary, 2) }} ج.م</td></tr>
                    @if($item->housing_allowance > 0)
                    <tr><td class="d-label">بدل سكن</td><td class="d-amount">{{ number_format($item->housing_allowance, 2) }} ج.م</td></tr>
                    @endif
                    @if($item->transport_allowance > 0)
                    <tr><td class="d-label">بدل مواصلات</td><td class="d-amount">{{ number_format($item->transport_allowance, 2) }} ج.م</td></tr>
                    @endif
                    @if($item->other_allowances > 0)
                    <tr><td class="d-label">بدلات أخرى</td><td class="d-amount">{{ number_format($item->other_allowances, 2) }} ج.م</td></tr>
                    @endif
                    @if($item->overtime_pay > 0)
                    <tr><td class="d-label">أجر أوفرتايم</td><td class="d-amount">{{ number_format($item->overtime_pay, 2) }} ج.م</td></tr>
                    @endif
                    <tr class="d-sub"><td class="d-label">إجمالي المستحقات</td><td class="d-amount">{{ number_format($item->gross_pay, 2) }} ج.م</td></tr>
                </table>
            </td>
            <td class="col-gap"></td>
            <td class="col-half">
                <div class="sec-title ded-title">الخصومات</div>
                <table class="detail-table">
                    @if($item->absence_deduction > 0)
                    <tr><td class="d-label">خصم غياب</td><td class="d-amount d-red">{{ number_format($item->absence_deduction, 2) }} ج.م</td></tr>
                    @endif
                    @if($item->other_deductions > 0)
                    <tr><td class="d-label">خصومات أخرى</td><td class="d-amount d-red">{{ number_format($item->other_deductions, 2) }} ج.م</td></tr>
                    @endif
                    @if($item->total_deductions == 0)
                    <tr><td colspan="2" class="d-muted">لا توجد خصومات</td></tr>
                    @endif
                    <tr class="d-sub"><td class="d-label">إجمالي الخصومات</td><td class="d-amount d-red">{{ number_format($item->total_deductions, 2) }} ج.م</td></tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- ── Net Pay ── --}}
    <table class="net-table">
        <tr>
            <td class="net-label">صافي الراتب المستحق</td>
            <td class="net-amount">{{ number_format($item->net_pay, 2) }} ج.م</td>
        </tr>
    </table>

    {{-- ── Signatures ── --}}
    <table class="sig-table">
        <tr>
            <td class="sig-cell">
                <div class="sig-line"></div>
                توقيع الموظف
            </td>
            <td class="sig-mid">طُبع: {{ now()->format('Y/m/d') }}</td>
            <td class="sig-cell">
                <div class="sig-line"></div>
                توقيع المدير المالي
            </td>
        </tr>
        <tr>
            <td colspan="3" style="text-align: center; font-size: 8px; color: #999; padding-top: 8px; border-top: 1px dashed #ccc; margin-top: 8px;">
                {{ \App\Models\Setting::get('invoice_footer', '') }}
            </td>
        </tr>
    </table>

</div>
</body>
</html>
