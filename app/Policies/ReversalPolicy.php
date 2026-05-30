<?php

namespace App\Policies;

use App\Models\Reversal;
use App\Models\User;

class ReversalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('reversals.view') || $user->can('create reversal');
    }

    public function view(User $user, Reversal $reversal): bool
    {
        return $user->can('reversals.view') || $user->can('create reversal');
    }

    public function create(User $user): bool
    {
        return $user->can('reversals.create') || $user->can('create reversal');
    }
}
