<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    protected function managementRoles(): array
    {
        return [
            Role::Administrador->value,
            Role::Tecnico->value,
            Role::Direccion->value,
            Role::Administracion->value,
            Role::Supervisor->value,
        ];
    }

    protected function rolesCanDelete(): array
    {
        return [
            Role::Administrador->value,
            Role::Tecnico->value,
        ];
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole($this->managementRoles());
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasAnyRole($this->managementRoles());
    }

    public function viewOptions(User $user): bool
    {
        return $user->hasAnyRole($this->managementRoles());
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole($this->managementRoles());
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasAnyRole($this->managementRoles());
    }

    public function delete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        return $user->hasAnyRole($this->rolesCanDelete());
    }

    public function restore(User $user, User $model): bool
    {
        return $user->hasAnyRole($this->managementRoles());
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $user->hasAnyRole($this->managementRoles());
    }
}
