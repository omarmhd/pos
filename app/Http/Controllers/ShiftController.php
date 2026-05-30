<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:hr.view_shifts')->only(['index']);
        $this->middleware('can:hr.manage_shifts')->only(['create', 'store', 'edit', 'update', 'destroy']);
    }

    public function index()
    {
        $shifts = Shift::orderBy('start_time')->get();
        return view('hr.shifts.index', compact('shifts'));
    }

    public function create()
    {
        return view('hr.shifts.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100|unique:shifts,name',
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i',
            'hours'      => 'required|integer|min:1|max:24',
        ]);

        Shift::create($data);

        return redirect()->route('hr.shifts.index')->with('success', 'تم إضافة الوردية بنجاح');
    }

    public function edit(Shift $shift)
    {
        return view('hr.shifts.edit', compact('shift'));
    }

    public function update(Request $request, Shift $shift)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100|unique:shifts,name,' . $shift->id,
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i',
            'hours'      => 'required|integer|min:1|max:24',
            'is_active'  => 'nullable|boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $shift->update($data);

        return redirect()->route('hr.shifts.index')->with('success', 'تم تحديث الوردية');
    }

    public function destroy(Shift $shift)
    {
        if ($shift->attendance()->exists()) {
            return back()->with('error', 'لا يمكن حذف الوردية لارتباطها بسجلات حضور');
        }

        $shift->delete();
        return back()->with('success', 'تم حذف الوردية');
    }
}
