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
     * Comercial: only if linked to a Salesperson (data scoping is applied in services).
     */
    public function viewAny(User $user): bool
    {
        if ($user->hasRole(Role::Comercial->value)) {
            return $user->salesperson !== null;
        }
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can view the order.
     * Comercial: only their own orders (salesperson_id matches).
     */
    public function view(User $user, Order $order): bool
    {
        if ($user->hasRole(Role::Comercial->value)) {
            return $user->salesperson && $order->salesperson_id === $user->salesperson->id;
        }
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
     * Comercial: cannot update.
     */
    public function update(User $user, Order $order): bool
    {
        if ($user->hasRole(Role::Comercial->value)) {
            return false;
        }
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can delete the order.
     * Comercial: cannot delete.
     */
    public function delete(User $user, Order $order): bool
    {
        if ($user->hasRole(Role::Comercial->value)) {
            return false;
        }
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can restore the order.
     * Comercial: cannot restore.
     */
    public function restore(User $user, Order $order): bool
    {
        if ($user->hasRole(Role::Comercial->value)) {
            return false;
        }
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can permanently delete the order.
     * Comercial: cannot force delete.
     */
    public function forceDelete(User $user, Order $order): bool
    {
        if ($user->hasRole(Role::Comercial->value)) {
            return false;
        }
        return $user->hasAnyRole($this->allowedRoles());
    }
}
