<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:roles.view')->only(['index']);
        $this->middleware('can:roles.create')->only(['create', 'store']);
        $this->middleware('can:roles.edit')->only(['edit', 'update']);
        $this->middleware('can:roles.delete')->only(['destroy']);
    }

    public function index()
    {
        $roles = Role::withCount('permissions')->orderBy('name')->get()
            ->each(fn($r) => $r->users_count = $r->users()->count());

        return view('roles.index', compact('roles'));
    }

    public function create()
    {
        $modules         = config('permission_groups');
        $copyFromRoles   = Role::orderBy('name')->get();
        $rolePermissions = [];

        return view('roles.form', compact('modules', 'copyFromRoles', 'rolePermissions'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'          => 'required|string|max:100|unique:roles,name',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role = Role::create(['name' => $request->name, 'guard_name' => 'web']);
        $role->syncPermissions($request->input('permissions', []));

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return redirect()->route('roles.index')
            ->with('success', "تم إنشاء الدور [{$role->name}] بنجاح");
    }

    public function edit(Role $role)
    {
        $modules         = config('permission_groups');
        $copyFromRoles   = Role::where('id', '!=', $role->id)->orderBy('name')->get();
        $rolePermissions = $role->permissions->pluck('name')->toArray();

        return view('roles.form', compact('modules', 'copyFromRoles', 'rolePermissions', 'role'));
    }

    public function update(Request $request, Role $role)
    {
        $request->validate([
            'name'          => 'required|string|max:100|unique:roles,name,' . $role->id,
            'permissions'   => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        if ($role->name === 'admin' && $request->name !== 'admin') {
            return back()->with('error', 'لا يمكن تغيير اسم دور المدير الرئيسي');
        }

        $role->update(['name' => $request->name]);
        $role->syncPermissions($request->input('permissions', []));

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return redirect()->route('roles.index')
            ->with('success', "تم تحديث الدور [{$role->name}] بنجاح");
    }

    public function destroy(Role $role)
    {
        if ($role->name === 'admin') {
            return back()->with('error', 'لا يمكن حذف دور المدير الرئيسي');
        }

        if ($role->users()->count() > 0) {
            return back()->with('error',
                "لا يمكن حذف الدور [{$role->name}] — يوجد {$role->users()->count()} مستخدم مرتبط به");
        }

        $role->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return redirect()->route('roles.index')->with('success', 'تم حذف الدور بنجاح');
    }

    /** AJAX: return permission names for a given role (for "copy from role" feature) */
    public function permissionsOf(Role $role)
    {
        return response()->json($role->permissions->pluck('name'));
    }
}
