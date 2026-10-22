<?php

namespace App\Policies;

use App\Models\User;

class GenieacsServerPolicy
{
    private const VIEW_ROLES = ['owner', 'admin'];

    private const MANAGE_ROLES = ['owner'];

    public function viewGenieacs(User $user): bool
    {
        return $user->active && in_array($user->role, self::VIEW_ROLES, true);
    }

    public function manageGenieacs(User $user): bool
    {
        return $user->active && in_array($user->role, self::MANAGE_ROLES, true);
    }
}
