<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Roles that always have global data access regardless of branch assignment.
     *
     * Mirrors the "Authorization Profile" concept in SAP/Oracle:
     *   - Admin & Manager:   Company-level authority → see everything
     *   - Reversal Manager:  Needs cross-branch visibility for reversal decisions
     *
     * Roles NOT listed here use context-based scoping:
     *   - accountant WITH branch → branch-locked (branch accountant)
     *   - accountant WITHOUT branch → global (general accountant)
     *   - cashier WITH branch → branch-locked (always)
     */
    protected const GLOBAL_ROLES = ['admin', 'manager', 'reversal_manager'];

    /**
     * Resolve the effective branch_id for data scoping.
     *
     * Priority chain (mirrors SAP Authorization Object logic):
     *
     *   1. Global role (admin/manager) → free filter, never locked
     *   2. Any role WITHOUT branch_id  → free filter (general accountant, etc.)
     *   3. Any role WITH branch_id     → locked to user's branch
     *
     * Returns:
     *   null  = no filter → user sees all branches (consolidated)
     *   int   = branch filter locked or chosen
     */
    protected function effectiveBranchId(Request $request): ?int
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if (!$user) {
            return null;
        }

        // Global roles: always unrestricted — admin assigned to a branch still sees all
        if ($user->hasAnyRole(static::GLOBAL_ROLES)) {
            return $request->filled('branch_id') ? (int) $request->input('branch_id') : null;
        }

        // Context-based: branch assignment determines scope
        if ($user->branch_id) {
            // Branch-assigned employee → locked to their branch
            return (int) $user->branch_id;
        }

        // No branch assigned, non-global role → sees all with optional filter
        // (e.g., general accountant who hasn't been assigned to a specific branch)
        return $request->filled('branch_id') ? (int) $request->input('branch_id') : null;
    }

    /**
     * True when the current user CANNOT change the branch filter.
     *
     * A user is locked when:
     *   - They are NOT a global-role user (admin/manager)
     *   - AND they have a branch_id assigned
     *
     * Global roles are NEVER locked even if they have branch_id on their record.
     */
    protected function isBranchLocked(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        if ($user->hasAnyRole(static::GLOBAL_ROLES)) {
            return false; // Global role → never locked
        }

        return $user->branch_id !== null; // Branch-assigned → locked
    }
}
