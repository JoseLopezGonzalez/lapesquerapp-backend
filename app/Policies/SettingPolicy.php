<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SettingPolicy
{
    use HandlesAuthorization;

    /**
     * Roles que pueden actualizar la configuración del tenant.
     */
    protected function rolesAllowedToUpdate(): array
    {
        return [
            Role::Administrador->value,
            Role::Tecnico->value,
        ];
    }

    /**
     * Determine if the user can list/view settings.
     * Cualquier usuario autenticado puede ver la configuración (necesaria en varias pantallas).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can update settings.
     */
    public function update(User $user): bool
    {
        return $user->hasAnyRole($this->rolesAllowedToUpdate());
    }
}
