<?php

/**
 * الخريطة المركزية لأكواد الحسابات (تدقيق H-1).
 *
 * مصدر واحد لكل مفاتيح إعدادات الحسابات وقيمها الافتراضية، بدل تناثر القيم الحرفية
 * في خدمات الترحيل. يقرأها أمر `accounts:verify` للتحقق من وجود ونشاط كل حساب مطلوب
 * قبل الاعتماد على النظام في الترحيل.
 *
 * البنية: setting_key => ['label' => '...', 'default' => 'CODE' | null]
 * default = null يعني لا يوجد افتراضي؛ يُعتبر "غير مُهيّأ" إن لم يُحدَّد في الإعدادات.
 */
return [
    // ── الأصول ───────────────────────────────────────────────────────────────
    'account_cash_code'                => ['label' => 'الصندوق',                 'default' => '1000'],
    'account_bank_code'                => ['label' => 'البنك',                   'default' => '1100'],
    'account_checks_receivable_code'   => ['label' => 'شيكات تحت التحصيل',       'default' => '1120'],
    'account_ar_code'                  => ['label' => 'ذمم العملاء',             'default' => '1200'],
    'account_source_discount_code'     => ['label' => 'خصم مصدر مدفوع مقدماً',   'default' => '1250'],
    'account_tax_input_code'           => ['label' => 'ض.ق.م مدخلات',            'default' => '1260'],
    'account_input_vat_code'           => ['label' => 'ض.ق.م مدخلات (مرادف)',    'default' => '1260'],
    'account_inventory_code'           => ['label' => 'مخزون بضاعة تجارية',      'default' => '1300'],
    'account_inventory_raw_code'       => ['label' => 'مخزون مواد خام',          'default' => '1310'],
    'account_inventory_finished_code'  => ['label' => 'مخزون منتجات تامة الصنع',  'default' => '1320'],
    'account_employee_loans_code'      => ['label' => 'سلف الموظفين',            'default' => null],

    // ── الالتزامات ───────────────────────────────────────────────────────────
    'account_ap_code'                  => ['label' => 'ذمم الموردين',            'default' => '2000'],
    'account_checks_payable_code'      => ['label' => 'شيكات مستحقة الدفع',      'default' => '2030'],
    'account_customer_deposits_code'   => ['label' => 'سُلَف/إيداعات العملاء',   'default' => '2050'],
    'account_tax_payable_code'         => ['label' => 'ض.ق.م مستحقة (مخرجات)',   'default' => '2200'],
    'account_salaries_payable_code'    => ['label' => 'رواتب مستحقة',            'default' => null],
    'account_eosb_provision_code'      => ['label' => 'مخصص نهاية الخدمة',       'default' => null],

    // ── حقوق الملكية ─────────────────────────────────────────────────────────
    'account_retained_earnings_code'   => ['label' => 'أرباح مرحّلة',            'default' => null],

    // ── الإيرادات ────────────────────────────────────────────────────────────
    'account_sales_code'               => ['label' => 'إيراد المبيعات',          'default' => '4000'],
    'account_purchase_returns_code'    => ['label' => 'مردودات المشتريات',       'default' => '4050'],
    'account_sales_returns_code'       => ['label' => 'مردودات المبيعات',        'default' => '4100'],
    'account_service_revenue_code'     => ['label' => 'إيرادات الخدمات',         'default' => '4200'],
    'account_discount_code'            => ['label' => 'خصم المبيعات',            'default' => '4300'],

    // ── المصروفات/التكلفة ────────────────────────────────────────────────────
    'account_cogs_code'                => ['label' => 'تكلفة البضاعة المباعة',   'default' => '5000'],
    'account_salaries_code'            => ['label' => 'مصروف الرواتب',           'default' => null],
    'account_eosb_expense_code'        => ['label' => 'مصروف نهاية الخدمة',      'default' => null],
    'account_inventory_shortage_code'  => ['label' => 'عجز المخزون',             'default' => null],
    'account_inventory_surplus_code'   => ['label' => 'فائض المخزون',            'default' => null],
    'account_pos_cash_shortage_code'   => ['label' => 'عجز صندوق نقطة البيع',    'default' => null],
    'account_pos_cash_overage_code'    => ['label' => 'فائض صندوق نقطة البيع',   'default' => null],
    'account_asset_gain_code'          => ['label' => 'أرباح بيع أصول',          'default' => null],
    'account_asset_loss_code'          => ['label' => 'خسائر بيع أصول',          'default' => null],
];
