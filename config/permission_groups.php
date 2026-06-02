<?php
/**
 * Permission groups configuration.
 * Used by RoleController to render the permission matrix UI.
 * Keys must match the permission names seeded in PermissionSeeder.
 */
return [
    'dashboard' => [
        'label' => 'لوحة التحكم',
        'icon'  => 'bi-speedometer2',
        'permissions' => [
            'dashboard.view' => 'عرض لوحة التحكم',
        ],
    ],
    'sales' => [
        'label' => 'المبيعات',
        'icon'  => 'bi-receipt',
        'permissions' => [
            'sales.view'           => 'عرض المبيعات',
            'sales.create'         => 'إنشاء فاتورة بيع (POS)',
            'sales.edit'           => 'تعديل المبيعات',
            'sales.delete'         => 'حذف المبيعات',
            'sales.post'           => 'ترحيل قيود المبيعات',
            'sales.reverse'        => 'عكس فواتير المبيعات',
            'sales.returns.view'   => 'عرض مرتجعات المبيعات',
            'sales.returns.create' => 'إنشاء مرتجع مبيعات',
        ],
    ],
    'purchases' => [
        'label' => 'المشتريات',
        'icon'  => 'bi-bag-plus',
        'permissions' => [
            'purchases.view'            => 'عرض المشتريات',
            'purchases.create'          => 'إنشاء فاتورة شراء',
            'purchases.edit'            => 'تعديل المشتريات',
            'purchases.delete'          => 'حذف المشتريات',
            'purchases.post'            => 'ترحيل قيود المشتريات',
            'purchases.returns.view'    => 'عرض مرتجعات المشتريات',
            'purchases.returns.create'  => 'إنشاء مرتجع مشتريات',
            'expenses.view'             => 'عرض فواتير المصروفات',
            'expenses.create'           => 'إنشاء فاتورة مصروف',
            'expenses.pay'              => 'تسجيل دفعة لفاتورة مصروف',
        ],
    ],
    'customers' => [
        'label' => 'العملاء',
        'icon'  => 'bi-people',
        'permissions' => [
            'customers.view'     => 'عرض العملاء',
            'customers.create'   => 'إضافة عميل',
            'customers.edit'     => 'تعديل العملاء',
            'customers.delete'   => 'حذف العملاء',
            'customers.payments' => 'تسجيل دفعات العملاء',
        ],
    ],
    'suppliers' => [
        'label' => 'الموردون',
        'icon'  => 'bi-building',
        'permissions' => [
            'suppliers.view'     => 'عرض الموردين',
            'suppliers.create'   => 'إضافة مورد',
            'suppliers.edit'     => 'تعديل الموردين',
            'suppliers.delete'   => 'حذف الموردين',
            'suppliers.payments' => 'تسجيل دفعات الموردين',
        ],
    ],
    'products' => [
        'label' => 'المنتجات',
        'icon'  => 'bi-box-seam',
        'permissions' => [
            'products.view'   => 'عرض المنتجات',
            'products.create' => 'إضافة منتج',
            'products.edit'   => 'تعديل المنتجات',
            'products.delete' => 'حذف المنتجات',
        ],
    ],
    'categories' => [
        'label' => 'الفئات',
        'icon'  => 'bi-tags',
        'permissions' => [
            'categories.view'   => 'عرض الفئات',
            'categories.create' => 'إضافة فئة',
            'categories.edit'   => 'تعديل الفئات',
            'categories.delete' => 'حذف الفئات',
        ],
    ],
    'inventory' => [
        'label' => 'المخزون والجرد',
        'icon'  => 'bi-archive',
        'permissions' => [
            'inventory.view'   => 'عرض تقارير المخزون',
            'inventory.adjust' => 'تعديل المخزون يدوياً وإنشاء قيود التسوية',
            'inventory.count'  => 'إنشاء جلسات الجرد الدوري واعتمادها',
        ],
    ],
    'vouchers' => [
        'label' => 'سندات القبض والصرف',
        'icon'  => 'bi-receipt-cutoff',
        'permissions' => [
            'vouchers.view'   => 'عرض سندات القبض والصرف',
            'vouchers.create' => 'إنشاء سند قبض أو صرف',
            'vouchers.delete' => 'حذف السندات (غير المُرحَّلة)',
        ],
    ],
    'accounts' => [
        'label' => 'دليل الحسابات',
        'icon'  => 'bi-list-columns-reverse',
        'permissions' => [
            'accounts.view'   => 'عرض دليل الحسابات',
            'accounts.create' => 'إضافة حساب',
            'accounts.edit'   => 'تعديل الحسابات',
            'accounts.delete' => 'حذف الحسابات',
            'accounts.import' => 'استيراد / تصدير دليل الحسابات',
        ],
    ],
    'journal_entries' => [
        'label' => 'القيود اليومية',
        'icon'  => 'bi-journal-text',
        'permissions' => [
            'journal_entries.view'   => 'عرض القيود اليومية',
            'journal_entries.create' => 'إنشاء قيد يومي',
        ],
    ],
    'ledger' => [
        'label' => 'دفتر الأستاذ',
        'icon'  => 'bi-journal-bookmark',
        'permissions' => [
            'ledger.view' => 'عرض دفتر الأستاذ',
        ],
    ],
    'trial_balance' => [
        'label' => 'ميزان المراجعة',
        'icon'  => 'bi-check2-square',
        'permissions' => [
            'trial_balance.view' => 'عرض ميزان المراجعة',
        ],
    ],
    'financial_statements' => [
        'label' => 'القوائم المالية',
        'icon'  => 'bi-bar-chart-line',
        'permissions' => [
            'financial_statements.view' => 'عرض القوائم المالية',
        ],
    ],
    'accounting' => [
        'label' => 'المحاسبة (متقدم)',
        'icon'  => 'bi-calculator',
        'permissions' => [
            'accounting.year_end_close' => 'إقفال نهاية السنة',
            'accounting.post'           => 'ترحيل القيود المحاسبية',
            'accounting.reverse'        => 'عكس القيود المحاسبية',
        ],
    ],
    'reports' => [
        'label' => 'التقارير',
        'icon'  => 'bi-graph-up',
        'permissions' => [
            'reports.view' => 'عرض جميع التقارير',
        ],
    ],
    'hr' => [
        'label' => 'الموارد البشرية',
        'icon'  => 'bi-person-badge',
        'permissions' => [
            'hr.view_employees'    => 'عرض الموظفين',
            'hr.create_employees'  => 'إضافة موظف',
            'hr.edit_employees'    => 'تعديل الموظفين',
            'hr.view_payroll'      => 'عرض مسير الرواتب',
            'hr.create_payroll'    => 'إنشاء كشف رواتب',
            'hr.approve_payroll'   => 'اعتماد الرواتب',
            'hr.view_attendance'   => 'عرض سجل الحضور',
            'hr.manage_attendance' => 'إدارة الحضور والانصراف',
            'hr.view_shifts'       => 'عرض الورديات',
            'hr.manage_shifts'     => 'إدارة الورديات',
            'hr.manage_loans'      => 'إدارة سلف الموظفين (صرف وإلغاء)',
        ],
    ],
    'users' => [
        'label' => 'المستخدمون',
        'icon'  => 'bi-people-fill',
        'permissions' => [
            'users.view'   => 'عرض المستخدمين',
            'users.create' => 'إضافة مستخدم',
            'users.edit'   => 'تعديل المستخدمين',
            'users.delete' => 'حذف المستخدمين',
        ],
    ],
    'roles' => [
        'label' => 'الأدوار والصلاحيات',
        'icon'  => 'bi-shield-lock',
        'permissions' => [
            'roles.view'   => 'عرض الأدوار والصلاحيات',
            'roles.create' => 'إنشاء دور جديد',
            'roles.edit'   => 'تعديل الأدوار',
            'roles.delete' => 'حذف الأدوار',
        ],
    ],
    'branches' => [
        'label' => 'الفروع والمخازن',
        'icon'  => 'bi-building-fill-check',
        'permissions' => [
            'branches.view'   => 'عرض الفروع والمخازن',
            'branches.manage' => 'إدارة الفروع والمخازن (إضافة/تعديل/حذف)',
        ],
    ],
    'price_lists' => [
        'label' => 'قوائم الأسعار',
        'icon'  => 'bi-tags-fill',
        'permissions' => [
            'price_lists.view'   => 'عرض قوائم الأسعار',
            'price_lists.manage' => 'إدارة قوائم الأسعار وتسعير الأصناف',
        ],
    ],
    'settings' => [
        'label' => 'إعدادات النظام',
        'icon'  => 'bi-gear',
        'permissions' => [
            'settings.view' => 'عرض الإعدادات',
            'settings.edit' => 'تعديل الإعدادات',
        ],
    ],
    'audit_logs' => [
        'label' => 'سجل التدقيق',
        'icon'  => 'bi-shield-check',
        'permissions' => [
            'audit_logs.view' => 'عرض سجل التدقيق',
        ],
    ],
    'reversals' => [
        'label' => 'قيود التصحيح والعكس',
        'icon'  => 'bi-arrow-counterclockwise',
        'permissions' => [
            'reversals.view'   => 'عرض قيود التصحيح',
            'reversals.create' => 'إنشاء قيد تصحيح / عكسي',
        ],
    ],
];
