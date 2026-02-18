<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CustomerPolicy
{
    use HandlesAuthorization;

    protected function allowedRoles(): array
    {
        return Role::values();
    }

    /**
     * Determine if the user can view any customers.
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
     * Determine if the user can view the customer.
     * Comercial: only their own customers (salesperson_id matches).
     */
    public function view(User $user, Customer $customer): bool
    {
        if ($user->hasRole(Role::Comercial->value)) {
            return $user->salesperson && $customer->salesperson_id === $user->salesperson->id;
        }
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can create customers.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can update the customer.
     * Comercial: cannot update.
     */
    public function update(User $user, Customer $customer): bool
    {
        if ($user->hasRole(Role::Comercial->value)) {
            return false;
        }
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can delete the customer.
     * Comercial: cannot delete.
     */
    public function delete(User $user, Customer $customer): bool
    {
        if ($user->hasRole(Role::Comercial->value)) {
            return false;
        }
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can restore the customer.
     * Comercial: cannot restore.
     */
    public function restore(User $user, Customer $customer): bool
    {
        if ($user->hasRole(Role::Comercial->value)) {
            return false;
        }
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can permanently delete the customer.
     * Comercial: cannot force delete.
     */
    public function forceDelete(User $user, Customer $customer): bool
    {
        if ($user->hasRole(Role::Comercial->value)) {
            return false;
        }
        return $user->hasAnyRole($this->allowedRoles());
    }
}
