<?php

namespace App\Policies;

use App\Models\OntDevice;
use App\Models\User;

class OntDevicePolicy
{
    private const VIEW_ROLES = ['owner', 'admin', 'teknisi'];

    private const MAPPING_ROLES = ['owner', 'teknisi'];

    private const TASK_ROLES = ['owner', 'teknisi'];

    public function viewAny(User $user): bool
    {
        return $user->active && in_array($user->role, self::VIEW_ROLES, true);
    }

    public function view(User $user, OntDevice $ontDevice): bool
    {
        return $this->viewAny($user);
    }

    public function manageMapping(User $user, OntDevice $ontDevice): bool
    {
        return $user->active && in_array($user->role, self::MAPPING_ROLES, true);
    }

    public function runTask(User $user, OntDevice $ontDevice): bool
    {
        return $user->active && in_array($user->role, self::MAPPING_ROLES, true);
    }
}
