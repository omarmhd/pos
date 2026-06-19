@extends('layouts.app')
@section('page-title', 'إعدادات النظام')

@php
use App\Models\Setting;

$sections = [

    // ── Store Identity ──────────────────────────────────────────────────────
    'store' => [
        'title' => 'هوية المتجر',
        'icon'  => 'bi-shop',
        'type'  => 'fields',
        'items' => [
            ['key' => 'store_name',       'label' => 'اسم المتجر',      'type' => 'text',   'placeholder' => 'سوبر ماركت'],
            ['key' => 'store_address',    'label' => 'العنوان',         'type' => 'text',   'placeholder' => 'شارع التحرير، المدينة'],
            ['key' => 'store_phone',      'label' => 'رقم الهاتف',     'type' => 'text',   'placeholder' => '0123456789'],
            ['key' => 'store_tax_number', 'label' => 'الرقم الضريبي',  'type' => 'text',   'placeholder' => ''],
        ],
    ],

    // ── Currency & Tax ──────────────────────────────────────────────────────
    'currency' => [
        'title' => 'العملة والضرائب',
        'icon'  => 'bi-currency-exchange',
        'type'  => 'fields',
        'items' => [
            ['key' => 'currency_symbol', 'label' => 'رمز العملة',                    'type' => 'text',   'placeholder' => 'ج.م', 'col' => 3],
            ['key' => 'vat_rate',        'label' => 'نسبة ضريبة القيمة المضافة (%)',  'type' => 'number', 'min' => 0, 'max' => 100, 'step' => '0.01', 'col' => 3],
            ['key' => 'vat_enabled',     'label' => 'تفعيل ضريبة القيمة المضافة',     'type' => 'toggle'],
            ['key' => 'vat_inclusive',   'label' => 'الضريبة مدمجة داخل الأسعار',     'type' => 'toggle'],
        ],
    ],

    // ── Sales & POS ─────────────────────────────────────────────────────────
    'pos' => [
        'title' => 'نقطة البيع والمبيعات',
        'icon'  => 'bi-bag-check',
        'type'  => 'fields',
        'items' => [
            ['key' => 'max_discount_percent',   'label' => 'أقصى نسبة خصم مسموح (%)',      'type' => 'number', 'min' => 0, 'max' => 100, 'col' => 3],
            ['key' => 'max_credit_days',         'label' => 'أقصى أيام ائتمان',             'type' => 'number', 'min' => 0, 'col' => 3],
            ['key' => 'receipt_footer',          'label' => 'نص ذيل إيصال البيع (POS)',     'type' => 'text',   'placeholder' => 'شكراً لزيارتكم'],
            ['key' => 'default_payment_method',  'label' => 'طريقة الدفع الافتراضية',       'type' => 'select',
             'options' => ['cash' => 'نقدي', 'card' => 'بطاقة بنكية', 'mobile_wallet' => 'محفظة إلكترونية'], 'col' => 3],
            ['key' => 'receipt_width_mm',        'label' => 'عرض الفاتورة',                'type' => 'select',
             'options' => ['58' => '58mm', '80' => '80mm'], 'col' => 3],
            ['key' => 'allow_negative_stock',    'label' => 'السماح ببيع المخزون السالب',    'type' => 'toggle'],
            ['key' => 'credit_sales_enabled',    'label' => 'تفعيل البيع الآجل',            'type' => 'toggle'],
        ],
    ],

    // ── Fiscal Year ─────────────────────────────────────────────────────────
    'fiscal' => [
        'title' => 'السنة المالية',
        'icon'  => 'bi-calendar-range',
        'type'  => 'fields',
        'items' => [
            ['key' => 'fiscal_year_start_month', 'label' => 'شهر بداية السنة المالية', 'type' => 'select', 'col' => 4,
             'options' => [
                '1'  => 'يناير',  '2'  => 'فبراير', '3'  => 'مارس',     '4'  => 'أبريل',
                '5'  => 'مايو',   '6'  => 'يونيو',  '7'  => 'يوليو',    '8'  => 'أغسطس',
                '9'  => 'سبتمبر', '10' => 'أكتوبر', '11' => 'نوفمبر',   '12' => 'ديسمبر',
             ]],
        ],
    ],

    // ── HR / Payroll ─────────────────────────────────────────────────────────
    'hr' => [
        'title' => 'الموارد البشرية والرواتب',
        'icon'  => 'bi-people',
        'type'  => 'fields',
        'items' => [
            ['key' => 'working_days_per_month',       'label' => 'أيام العمل في الشهر',                    'type' => 'number', 'min' => 1, 'max' => 31, 'col' => 3],
            ['key' => 'overtime_rate_multiplier',     'label' => 'معامل أجر الساعات الإضافية (×)',          'type' => 'number', 'min' => 1, 'max' => 5, 'step' => '0.01', 'col' => 3],
            ['key' => 'social_insurance_employee_pct','label' => 'نسبة التأمينات الاجتماعية — الموظف (%)',  'type' => 'number', 'min' => 0, 'max' => 100, 'step' => '0.01', 'col' => 3],
            ['key' => 'social_insurance_employer_pct','label' => 'نسبة التأمينات الاجتماعية — صاحب العمل (%)','type' => 'number', 'min' => 0, 'max' => 100, 'step' => '0.01', 'col' => 3],
        ],
    ],

    // ── Inventory / Customers ─────────────────────────────────────────────
    'inventory' => [
        'title' => 'المخزون والعملاء',
        'icon'  => 'bi-box-seam',
        'type'  => 'fields',
        'items' => [
            ['key' => 'default_min_stock_quantity', 'label' => 'الحد الأدنى للمخزون الافتراضي',     'type' => 'number', 'min' => 0, 'col' => 4],
            ['key' => 'expiry_alert_days',           'label' => 'تنبيه انتهاء الصلاحية قبل (أيام)', 'type' => 'number', 'min' => 0, 'col' => 4],
            ['key' => 'default_credit_limit',        'label' => 'حد ائتمان العميل الافتراضي',       'type' => 'number', 'min' => 0, 'step' => '0.01', 'col' => 4],
        ],
    ],

    // ── Accounting GL Mappings ───────────────────────────────────────────────
    'revenue_expense' => [
        'title' => 'حسابات الإيرادات والتكاليف',
        'icon'  => 'bi-graph-up-arrow',
        'type'  => 'accounts',
        'items' => [
            ['key' => 'account_sales_code',    'label' => 'حساب المبيعات'],
            ['key' => 'account_cogs_code',     'label' => 'حساب تكلفة البضاعة المباعة (COGS)'],
            ['key' => 'account_discount_code', 'label' => 'حساب خصم المبيعات'],
        ],
    ],
    'assets' => [
        'title' => 'حسابات الأصول',
        'icon'  => 'bi-bank',
        'type'  => 'accounts',
        'items' => [
            ['key' => 'account_cash_code',                'label' => 'حساب الصندوق (نقد)'],
            ['key' => 'account_bank_code',                'label' => 'حساب البنك'],
            ['key' => 'account_ar_code',                  'label' => 'حساب العملاء (ذمم مدينة)'],
            ['key' => 'account_inventory_code',           'label' => 'حساب المخزون'],
            ['key' => 'account_input_vat_code',           'label' => 'حساب ضريبة القيمة المضافة المدخلات'],
            ['key' => 'account_checks_receivable_code',   'label' => 'حساب شيكات تحت التحصيل (افتراضي: 1120)'],
            ['key' => 'account_checks_payable_code',      'label' => 'حساب شيكات مستحقة الدفع (افتراضي: 2030)'],
        ],
    ],
    'liabilities' => [
        'title' => 'حسابات الالتزامات',
        'icon'  => 'bi-credit-card',
        'type'  => 'accounts',
        'items' => [
            ['key' => 'account_ap_code',               'label' => 'حساب الموردين (ذمم دائنة)'],
            ['key' => 'account_tax_payable_code',      'label' => 'حساب ضريبة القيمة المضافة المستحقة'],
            ['key' => 'account_salaries_payable_code', 'label' => 'حساب الرواتب المستحقة الدفع'],
        ],
    ],
    'expenses_equity' => [
        'title' => 'حسابات المصاريف وحقوق الملكية',
        'icon'  => 'bi-person-badge',
        'type'  => 'accounts',
        'items' => [
            ['key' => 'account_salaries_code',          'label' => 'حساب مصروف الرواتب'],
            ['key' => 'account_retained_earnings_code', 'label' => 'حساب الأرباح المحتجزة'],
        ],
    ],
    // ── حسابات يستخدمها النظام تلقائيًا في الترحيل (تُضبط هنا بدل القيم الافتراضية) ──
    'accounting_auto' => [
        'title' => 'حسابات تلقائية إضافية (جرد، شيكات، أصول، نهاية خدمة، عملاء)',
        'icon'  => 'bi-diagram-3',
        'type'  => 'accounts',
        'items' => [
            ['key' => 'account_customer_deposits_code', 'label' => 'حساب إيداعات/سُلَف العملاء — التزام (افتراضي: 2050)'],
            ['key' => 'account_sales_returns_code',     'label' => 'حساب مردودات المبيعات (افتراضي: 4200)'],
            ['key' => 'account_service_revenue_code',   'label' => 'حساب إيراد الخدمات (افتراضي: 4400)'],
            ['key' => 'account_source_discount_code',   'label' => 'حساب الخصم المكتسب من المورّد (افتراضي: 1250)'],
            ['key' => 'account_employee_loans_code',    'label' => 'حساب سُلَف الموظفين — أصل (افتراضي: 1250)'],
            ['key' => 'account_inventory_shortage_code','label' => 'حساب عجز الجرد — مصروف (افتراضي: 6510)'],
            ['key' => 'account_inventory_surplus_code', 'label' => 'حساب فائض الجرد — إيراد (افتراضي: 4100)'],
            ['key' => 'account_pos_cash_shortage_code', 'label' => 'حساب عجز نقدية الكاشير — مصروف (افتراضي: 6530)'],
            ['key' => 'account_pos_cash_overage_code',  'label' => 'حساب زيادة نقدية الكاشير — إيراد (افتراضي: 4160)'],
            ['key' => 'account_asset_gain_code',        'label' => 'حساب أرباح بيع الأصول الثابتة (افتراضي: 4150)'],
            ['key' => 'account_asset_loss_code',        'label' => 'حساب خسائر بيع الأصول الثابتة (افتراضي: 6520)'],
            ['key' => 'account_eosb_expense_code',      'label' => 'حساب مصروف مكافأة نهاية الخدمة (افتراضي: 6300)'],
            ['key' => 'account_eosb_provision_code',    'label' => 'حساب مخصص مكافأة نهاية الخدمة — التزام (افتراضي: 2200)'],
        ],
    ],
];
@endphp

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-gear-fill"></i> إعدادات النظام</h4>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<form method="POST" action="{{ route('settings.update') }}">
    @csrf

    @foreach($sections as $sectionKey => $section)
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-2">
            <span class="fw-semibold">
                <i class="bi {{ $section['icon'] }} me-1"></i>
                {{ $section['title'] }}
            </span>
        </div>
        <div class="card-body">
            <div class="row g-3">

                @if($section['type'] === 'accounts')
                    {{-- Account dropdown selects --}}
                    @foreach($section['items'] as $field)
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">{{ $field['label'] }}</label>
                        <select name="{{ $field['key'] }}" class="form-select form-select-sm">
                            <option value="">— لم يُحدَّد —</option>
                            @foreach($accounts as $acc)
                            <option value="{{ $acc->code }}"
                                {{ Setting::get($field['key']) === $acc->code ? 'selected' : '' }}>
                                {{ $acc->code }} — {{ $acc->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    @endforeach

                @elseif($section['type'] === 'fields')
                    {{-- Generic fields: text / number / select / toggle --}}
                    @foreach($section['items'] as $field)
                    @php
                        $colMd   = $field['col'] ?? 6;
                        $current = Setting::get($field['key'], '');
                    @endphp

                    @if($field['type'] === 'toggle')
                    <div class="col-md-6">
                        <div class="form-check form-switch mt-2">
                            <input type="hidden" name="{{ $field['key'] }}" value="0">
                            <input class="form-check-input" type="checkbox"
                                   name="{{ $field['key'] }}" id="field_{{ $field['key'] }}"
                                   value="1" {{ $current == '1' ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold small" for="field_{{ $field['key'] }}">
                                {{ $field['label'] }}
                            </label>
                        </div>
                    </div>

                    @elseif($field['type'] === 'select')
                    <div class="col-md-{{ $colMd }}">
                        <label class="form-label fw-semibold small">{{ $field['label'] }}</label>
                        <select name="{{ $field['key'] }}" class="form-select form-select-sm">
                            @foreach($field['options'] as $val => $label)
                            <option value="{{ $val }}" {{ $current == $val ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    @elseif($field['type'] === 'number')
                    <div class="col-md-{{ $colMd }}">
                        <label class="form-label fw-semibold small">{{ $field['label'] }}</label>
                        <input type="number"
                               name="{{ $field['key'] }}"
                               class="form-control form-control-sm"
                               value="{{ $current }}"
                               min="{{ $field['min'] ?? 0 }}"
                               {{ isset($field['max'])  ? 'max="'.$field['max'].'"'   : '' }}
                               {{ isset($field['step']) ? 'step="'.$field['step'].'"' : '' }}>
                    </div>

                    @else {{-- text --}}
                    <div class="col-md-{{ $colMd }}">
                        <label class="form-label fw-semibold small">{{ $field['label'] }}</label>
                        <input type="text"
                               name="{{ $field['key'] }}"
                               class="form-control form-control-sm"
                               value="{{ $current }}"
                               placeholder="{{ $field['placeholder'] ?? '' }}">
                    </div>
                    @endif

                    @endforeach
                @endif

            </div>
        </div>
    </div>
    @endforeach

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save"></i> حفظ الإعدادات
        </button>
    </div>
</form>
@endsection
