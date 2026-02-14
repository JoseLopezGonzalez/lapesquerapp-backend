<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Roles que pueden acceder a las acciones de Order (misma regla que middleware de rutas).
     * Cuando se aborde el bloque Sales/Orders se podrá refinar (p. ej. por salesperson_id).
     */
    protected function allowedRoles(): array
    {
        return Role::values();
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function view(User $user, Order $order): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function update(User $user, Order $order): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function restore(User $user, Order $order): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function forceDelete(User $user, Order $order): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }
}
