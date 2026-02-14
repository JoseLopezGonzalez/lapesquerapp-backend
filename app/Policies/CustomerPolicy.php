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
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can view the customer.
     */
    public function view(User $user, Customer $customer): bool
    {
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
     */
    public function update(User $user, Customer $customer): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can delete the customer.
     */
    public function delete(User $user, Customer $customer): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can restore the customer.
     */
    public function restore(User $user, Customer $customer): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can permanently delete the customer.
     */
    public function forceDelete(User $user, Customer $customer): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }
}
