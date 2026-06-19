<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Shift;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:hr.view_employees')->only(['index', 'show']);
        $this->middleware('can:hr.create_employees')->only(['create', 'store']);
        $this->middleware('can:hr.edit_employees')->only(['edit', 'update']);
    }

    public function index()
    {
        $employees = Employee::orderBy('name')->get();
        return view('hr.employees.index', compact('employees'));
    }

    public function create()
    {
        $shifts = Shift::where('is_active', true)->get();
        return view('hr.employees.create', compact('shifts'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                => 'required|string|max:255',
            'national_id'         => 'nullable|string|max:30|unique:employees,national_id',
            'phone'               => 'nullable|string|max:30',
            'email'               => 'nullable|email|max:255',
            'address'             => 'nullable|string',
            'hire_date'           => 'required|date',
            'employment_type'     => 'required|in:full_time,part_time,contract',
            'pay_type'            => 'required|in:monthly,daily,hourly',
            'base_salary'         => 'required|numeric|min:0',
            'housing_allowance'   => 'nullable|numeric|min:0',
            'transport_allowance' => 'nullable|numeric|min:0',
            'other_allowances'    => 'nullable|numeric|min:0',
            'bank_account'        => 'nullable|string|max:50',
            'department'          => 'nullable|string|max:100',
            'job_title'           => 'nullable|string|max:100',
            'notes'               => 'nullable|string',
            'eosb_salary_base'           => 'nullable|in:basic,gross',
            'eosb_tiers'                 => 'nullable|array',
            'eosb_tiers.*.to_year'       => 'nullable|integer|min:1',
            'eosb_tiers.*.days_per_year' => 'nullable|numeric|min:0',
        ]);

        $data['housing_allowance']   = $data['housing_allowance']   ?? 0;
        $data['transport_allowance'] = $data['transport_allowance'] ?? 0;
        $data['other_allowances']    = $data['other_allowances']    ?? 0;
        $this->applyEosbOverride($data, $request);

        Employee::create($data);

        return redirect()->route('hr.employees.index')->with('success', 'تم إضافة الموظف بنجاح');
    }

    public function show(Employee $employee)
    {
        $recentPayroll = $employee->payrollItems()
            ->with('payrollRun')
            ->orderByDesc('created_at')
            ->take(6)
            ->get();

        return view('hr.employees.show', compact('employee', 'recentPayroll'));
    }

    public function edit(Employee $employee)
    {
        return view('hr.employees.edit', compact('employee'));
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'name'                => 'required|string|max:255',
            'national_id'         => 'nullable|string|max:30|unique:employees,national_id,' . $employee->id,
            'phone'               => 'nullable|string|max:30',
            'email'               => 'nullable|email|max:255',
            'address'             => 'nullable|string',
            'hire_date'           => 'required|date',
            'termination_date'    => 'nullable|date|after_or_equal:hire_date',
            'employment_type'     => 'required|in:full_time,part_time,contract',
            'pay_type'            => 'required|in:monthly,daily,hourly',
            'base_salary'         => 'required|numeric|min:0',
            'housing_allowance'   => 'nullable|numeric|min:0',
            'transport_allowance' => 'nullable|numeric|min:0',
            'other_allowances'    => 'nullable|numeric|min:0',
            'bank_account'        => 'nullable|string|max:50',
            'department'          => 'nullable|string|max:100',
            'job_title'           => 'nullable|string|max:100',
            'is_active'           => 'nullable|boolean',
            'notes'               => 'nullable|string',
            'eosb_salary_base'           => 'nullable|in:basic,gross',
            'eosb_tiers'                 => 'nullable|array',
            'eosb_tiers.*.to_year'       => 'nullable|integer|min:1',
            'eosb_tiers.*.days_per_year' => 'nullable|numeric|min:0',
        ]);

        $data['is_active']           = $request->boolean('is_active');
        $data['housing_allowance']   = $data['housing_allowance']   ?? 0;
        $data['transport_allowance'] = $data['transport_allowance'] ?? 0;
        $data['other_allowances']    = $data['other_allowances']    ?? 0;
        $this->applyEosbOverride($data, $request);

        $employee->update($data);

        return redirect()->route('hr.employees.show', $employee)->with('success', 'تم تحديث بيانات الموظف');
    }

    /**
     * تطبيع تجاوز مكافأة نهاية الخدمة على مستوى الموظف.
     * يترك القيم فارغة (null) ليتّبع الموظف الإعداد العام.
     */
    private function applyEosbOverride(array &$data, Request $request): void
    {
        $data['eosb_salary_base'] = $request->input('eosb_salary_base') ?: null;

        $tiers = collect($request->input('eosb_tiers', []))
            ->filter(fn($r) => isset($r['days_per_year']) && (float) $r['days_per_year'] > 0)
            ->map(fn($r) => [
                'to_year'       => (($r['to_year'] ?? '') === '' || ($r['to_year'] ?? null) === null)
                                    ? null : (int) $r['to_year'],
                'days_per_year' => (float) $r['days_per_year'],
            ])->values()->all();

        $data['eosb_tiers'] = empty($tiers) ? null : $tiers;
    }
}
