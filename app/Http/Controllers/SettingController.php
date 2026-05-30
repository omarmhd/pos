<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Account;
use App\Models\Setting;

class SettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:settings.view')->only(['edit']);
        $this->middleware('can:settings.edit')->only(['update']);
    }

    public function edit()
    {
        $accounts = Account::orderBy('code')->get();
        return view('settings.edit', compact('accounts'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            // ── Accounting GL mappings ───────────────────────────────────────
            'account_sales_code'             => 'nullable|string',
            'account_cogs_code'              => 'nullable|string',
            'account_inventory_code'         => 'nullable|string',
            'account_cash_code'              => 'nullable|string',
            'account_bank_code'              => 'nullable|string',
            'account_ar_code'                => 'nullable|string',
            'account_ap_code'                => 'nullable|string',
            'account_salaries_code'          => 'nullable|string',
            'account_salaries_payable_code'  => 'nullable|string',
            'account_discount_code'          => 'nullable|string',
            'account_tax_payable_code'       => 'nullable|string',
            'account_retained_earnings_code' => 'nullable|string',
            'account_input_vat_code'         => 'nullable|string',
            // ── Store Identity ──────────────────────────────────────────────
            'store_name'                     => 'nullable|string|max:100',
            'store_address'                  => 'nullable|string|max:255',
            'store_phone'                    => 'nullable|string|max:30',
            'store_tax_number'               => 'nullable|string|max:50',
            // ── Currency & Tax ──────────────────────────────────────────────
            'currency_symbol'                => 'nullable|string|max:10',
            'vat_enabled'                    => 'nullable|in:0,1',
            'vat_rate'                       => 'nullable|numeric|min:0|max:100',
            'vat_inclusive'                  => 'nullable|in:0,1',
            // ── Sales & POS ─────────────────────────────────────────────────
            'allow_negative_stock'           => 'nullable|in:0,1',
            'max_discount_percent'           => 'nullable|numeric|min:0|max:100',
            'default_payment_method'         => 'nullable|in:cash,card,mobile_wallet',
            'credit_sales_enabled'           => 'nullable|in:0,1',
            'max_credit_days'                => 'nullable|integer|min:0',
            'receipt_footer'                 => 'nullable|string|max:255',
            'receipt_width_mm'               => 'nullable|integer|in:58,80',
            // ── Fiscal Year ─────────────────────────────────────────────────
            'fiscal_year_start_month'        => 'nullable|integer|between:1,12',
            // ── HR / Payroll ─────────────────────────────────────────────────
            'working_days_per_month'         => 'nullable|integer|min:1|max:31',
            'overtime_rate_multiplier'       => 'nullable|numeric|min:1|max:5',
            'social_insurance_employee_pct'  => 'nullable|numeric|min:0|max:100',
            'social_insurance_employer_pct'  => 'nullable|numeric|min:0|max:100',
            // ── Inventory / Customers ────────────────────────────────────────
            'default_min_stock_quantity'     => 'nullable|integer|min:0',
            'expiry_alert_days'              => 'nullable|integer|min:0',
            'default_credit_limit'           => 'nullable|numeric|min:0',
        ]);

        // Boolean toggle fields: hidden+checkbox sends '0' or '1'; ensure always stored.
        $boolFields = ['vat_enabled', 'vat_inclusive', 'allow_negative_stock', 'credit_sales_enabled'];
        foreach ($boolFields as $field) {
            $data[$field] = $request->input($field, '0') === '1' ? '1' : '0';
        }

        foreach ($data as $k => $v) {
            $old = \App\Models\Setting::where('key', $k)->first();
            Setting::set($k, $v);
            \App\Models\AuditLog::create([
                'user_id'        => auth()->id(),
                'auditable_type' => '\\App\\Models\\Setting',
                'auditable_id'   => $old?->id ?? null,
                'action'         => 'updated',
                'old_values'     => $old ? (object) $old->toArray() : null,
                'new_values'     => (object) ['key' => $k, 'value' => $v],
                'ip_address'     => request()->ip() ?? null,
            ]);
        }

        return redirect()->route('settings.edit')->with('success', 'تم حفظ الإعدادات بنجاح');
    }
}
