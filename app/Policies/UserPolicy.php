<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Roles que pueden ver y gestionar usuarios (listar, ver, crear, actualizar).
     */
    protected function allowedRoles(): array
    {
        return Role::values();
    }

    /**
     * Roles que pueden eliminar usuarios (solo administrador y técnico).
     */
    protected function rolesCanDelete(): array
    {
        return [
            Role::Administrador->value,
            Role::Tecnico->value,
        ];
    }

    /**
     * Determine if the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        if ($user->hasRole(Role::Comercial->value)) {
            return false;
        }
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can view the user model.
     */
    public function view(User $user, User $model): bool
    {
        if ($user->hasRole(Role::Comercial->value)) {
            return false;
        }
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function viewOptions(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can create users.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can update the user model.
     */
    public function update(User $user, User $model): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can delete the user model.
     * Only administrador and tecnico can delete; user cannot delete themselves.
     */
    public function delete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }
        return $user->hasAnyRole($this->rolesCanDelete());
    }

    /**
     * Determine if the user can restore the user model.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can permanently delete the user model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }
}
