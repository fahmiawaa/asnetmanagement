<?php

namespace App\Policies;

use App\Models\DeviceTask;
use App\Models\User;

class DeviceTaskPolicy
{
    private const VIEW_ROLES = ['owner', 'admin', 'teknisi'];

    private const MANAGE_ROLES = ['owner', 'teknisi'];

    public function viewAny(User $user): bool
    {
        return $user->active && in_array($user->role, self::VIEW_ROLES, true);
    }

    public function view(User $user, DeviceTask $deviceTask): bool
    {
        return $this->viewAny($user);
    }

    public function cancel(User $user, DeviceTask $deviceTask): bool
    {
        return $user->active && in_array($user->role, self::MANAGE_ROLES, true);
    }
}
