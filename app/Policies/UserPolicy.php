<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view the list of users.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can change another user's role.
     */
    public function updateRole(User $user, User $target): bool
    {
        return $user->isAdmin() && $user->isNot($target);
    }
}
