<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            // Store Identity
            'store_name'                     => 'سوبر ماركت',
            'store_address'                  => 'شارع التحرير، المدينة',
            'store_phone'                    => '0123456789',
            'store_tax_number'               => '',
            // Currency & Tax
            'currency_symbol'                => 'ج.م',
            'vat_enabled'                    => '0',
            'vat_rate'                       => '15',
            'vat_inclusive'                  => '0',
            // Sales & POS
            'allow_negative_stock'           => '0',
            'max_discount_percent'           => '100',
            'default_payment_method'         => 'cash',
            'credit_sales_enabled'           => '1',
            'max_credit_days'                => '30',
            'receipt_footer'                 => 'شكراً لزيارتكم',
            'receipt_width_mm'               => '80',
            // Fiscal Year
            'fiscal_year_start_month'        => '1',
            // HR / Payroll
            'working_days_per_month'         => '26',
            'overtime_rate_multiplier'       => '1.5',
            'social_insurance_employee_pct'  => '9',
            'social_insurance_employer_pct'  => '21',
            // Inventory / Customers
            'default_min_stock_quantity'     => '5',
            'expiry_alert_days'              => '30',
            'default_credit_limit'           => '0',
        ];

        foreach ($defaults as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'store_name', 'store_address', 'store_phone', 'store_tax_number',
            'currency_symbol', 'vat_enabled', 'vat_rate', 'vat_inclusive',
            'allow_negative_stock', 'max_discount_percent', 'default_payment_method',
            'credit_sales_enabled', 'max_credit_days', 'receipt_footer', 'receipt_width_mm',
            'fiscal_year_start_month',
            'working_days_per_month', 'overtime_rate_multiplier',
            'social_insurance_employee_pct', 'social_insurance_employer_pct',
            'default_min_stock_quantity', 'expiry_alert_days', 'default_credit_limit',
        ])->delete();
    }
};
