<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index()
    {
        $users = User::orderBy('name')->get();
        $permission = Permission::firstOrCreate(['name' => 'create reversal']);
        return view('permissions.index', compact('users', 'permission'));
    }

    public function toggle(Request $request, $userId)
    {
        $this->authorize('manage', User::class);
        $user = User::findOrFail($userId);
        $perm = Permission::firstOrCreate(['name' => 'create reversal']);

        if ($user->hasPermissionTo($perm)) {
            $user->revokePermissionTo($perm);
        } else {
            $user->givePermissionTo($perm);
        }

        return back();
    }
}
