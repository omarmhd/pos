<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:users.view')->only(['index', 'show']);
        $this->middleware('can:users.create')->only(['create', 'store']);
        $this->middleware('can:users.edit')->only(['edit', 'update']);
        $this->middleware('can:users.delete')->only(['destroy']);
    }

    public function index()
    {
        $users = User::with('roles', 'branch:id,name,code', 'defaultWarehouse:id,name')->orderBy('name')->get();
        return view('users.index', compact('users'));
    }

    public function create()
    {
        $roles      = Role::orderBy('name')->get();
        $branches   = Branch::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'type']);
        $warehouses = Warehouse::where('is_active', true)->with('branch:id,name')
                        ->orderBy('name')->get(['id', 'name', 'branch_id']);
        $terminals  = \App\Models\PosTerminal::where('is_active', true)
                        ->with('warehouse:id,name')
                        ->orderBy('name')->get();
        return view('users.create', compact('roles', 'branches', 'warehouses', 'terminals'));
    }

    public function store(Request $request)
    {
        $roleNames = Role::pluck('name')->toArray();

        $request->validate([
            'name'                 => 'required|string|max:255',
            'email'                => 'required|email|unique:users,email',
            'password'             => 'required|string|min:8|confirmed',
            'roles'                => 'required|array|min:1',
            'roles.*'              => 'string|in:' . implode(',', $roleNames),
            'is_active'            => 'boolean',
            'branch_id'            => 'nullable|exists:branches,id',
            'pos_terminal_id'      => 'nullable|exists:pos_terminals,id',
            'default_warehouse_id' => 'nullable|exists:warehouses,id',
        ]);

        $user = User::create([
            'name'                 => $request->name,
            'email'                => $request->email,
            'password'             => Hash::make($request->password),
            'role'                 => $request->roles[0],
            'is_active'            => $request->boolean('is_active'),
            'branch_id'            => $request->input('branch_id')            ?: null,
            'pos_terminal_id'      => $request->input('pos_terminal_id')      ?: null,
            'default_warehouse_id' => $request->input('default_warehouse_id') ?: null,
        ]);

        $user->syncRoles($request->roles);

        return redirect()->route('users.index')
            ->with('success', 'تم إضافة المستخدم بنجاح');
    }

    public function edit(User $user)
    {
        $roles      = Role::orderBy('name')->get();
        $userRoles  = $user->roles->pluck('name')->toArray();
        $branches   = Branch::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'type']);
        $warehouses = Warehouse::where('is_active', true)->with('branch:id,name')
                        ->orderBy('name')->get(['id', 'name', 'branch_id']);
        $terminals  = \App\Models\PosTerminal::where('is_active', true)
                        ->with('warehouse:id,name')
                        ->orderBy('name')->get();
        return view('users.edit', compact('user', 'roles', 'userRoles', 'branches', 'warehouses', 'terminals'));
    }

    public function update(Request $request, User $user)
    {
        $roleNames = Role::pluck('name')->toArray();

        $request->validate([
            'name'                 => 'required|string|max:255',
            'email'                => 'required|email|unique:users,email,' . $user->id,
            'password'             => 'nullable|string|min:8|confirmed',
            'roles'                => 'required|array|min:1',
            'roles.*'              => 'string|in:' . implode(',', $roleNames),
            'is_active'            => 'boolean',
            'branch_id'            => 'nullable|exists:branches,id',
            'pos_terminal_id'      => 'nullable|exists:pos_terminals,id',
            'default_warehouse_id' => 'nullable|exists:warehouses,id',
        ]);

        $data = [
            'name'                 => $request->name,
            'email'                => $request->email,
            'role'                 => $request->roles[0],
            'branch_id'            => $request->input('branch_id')            ?: null,
            'pos_terminal_id'      => $request->input('pos_terminal_id')      ?: null,
            'default_warehouse_id' => $request->input('default_warehouse_id') ?: null,
            'is_active'            => $request->boolean('is_active'),
        ];
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);
        $user->syncRoles($request->roles);

        return redirect()->route('users.index')
            ->with('success', 'تم تحديث المستخدم بنجاح');
    }

    public function destroy(User $user)
    {
        if ($user->id === \Illuminate\Support\Facades\Auth::id()) {
            return back()->with('error', 'لا يمكنك حذف حسابك الخاص');
        }

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'تم حذف المستخدم بنجاح');
    }
}
