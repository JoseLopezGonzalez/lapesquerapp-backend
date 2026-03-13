<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\ExternalUser;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExternalUserPolicy
{
    use HandlesAuthorization;

    protected function managementRoles(): array
    {
        return [
            Role::Administrador->value,
            Role::Tecnico->value,
            Role::Direccion->value,
            Role::Administracion->value,
        ];
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole($this->managementRoles());
    }

    public function view(User $user, ExternalUser $externalUser): bool
    {
        return $user->hasAnyRole($this->managementRoles());
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole($this->managementRoles());
    }

    public function update(User $user, ExternalUser $externalUser): bool
    {
        return $user->hasAnyRole($this->managementRoles());
    }

    public function delete(User $user, ExternalUser $externalUser): bool
    {
        return $user->hasAnyRole([
            Role::Administrador->value,
            Role::Tecnico->value,
        ]);
    }
}
