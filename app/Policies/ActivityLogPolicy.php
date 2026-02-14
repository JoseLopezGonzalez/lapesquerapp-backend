<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ActivityLogPolicy
{
    use HandlesAuthorization;

    /**
     * Roles que pueden ver el registro de actividad (auditoría).
     */
    protected function rolesCanViewAny(): array
    {
        return [
            Role::Administrador->value,
            Role::Tecnico->value,
            Role::Direccion->value,
        ];
    }

    /**
     * Determine if the user can list activity logs.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole($this->rolesCanViewAny());
    }
}
