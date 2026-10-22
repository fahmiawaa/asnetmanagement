<?php

namespace App\Policies;

use App\Models\User;

class NetworkNodePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->active && in_array($user->role, ['owner', 'admin', 'teknisi'], true);
    }

    public function manage(User $user): bool
    {
        return $user->active && in_array($user->role, ['owner', 'teknisi'], true);
    }
}
