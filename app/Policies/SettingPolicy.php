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
     * Roles que pueden ver y actualizar la configuración del tenant.
     */
    protected function rolesAllowed(): array
    {
        return [
            Role::Administrador->value,
            Role::Tecnico->value,
        ];
    }

    /**
     * Determine if the user can list/view settings.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole($this->rolesAllowed());
    }

    /**
     * Determine if the user can update settings.
     */
    public function update(User $user): bool
    {
        return $user->hasAnyRole($this->rolesAllowed());
    }
}
