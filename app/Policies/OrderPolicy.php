<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrderPolicy
{
    use HandlesAuthorization;

    /**
     * Roles que pueden acceder a las acciones de Order (misma regla que middleware de rutas).
     * Cuando se aborde el bloque Sales/Orders se podrá refinar (p. ej. por salesperson_id).
     */
    protected function allowedRoles(): array
    {
        return Role::values();
    }

    /**
     * Determine if the user can view any orders.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can view the order.
     */
    public function view(User $user, Order $order): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can create orders.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can update the order.
     */
    public function update(User $user, Order $order): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can delete the order.
     */
    public function delete(User $user, Order $order): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can restore the order.
     */
    public function restore(User $user, Order $order): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can permanently delete the order.
     */
    public function forceDelete(User $user, Order $order): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }
}
