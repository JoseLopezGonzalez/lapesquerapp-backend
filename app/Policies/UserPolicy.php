<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class UserPolicy
{
    /**
     * Roles que pueden acceder a las acciones de User (misma regla que middleware de rutas).
     * Se podrá refinar cuando se aborde el apartado User en profundidad (p. ej. solo administrador puede eliminar).
     */
    protected function allowedRoles(): array
    {
        return Role::values();
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function restore(User $user, User $model): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }
}
