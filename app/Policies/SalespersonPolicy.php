<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Salesperson;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SalespersonPolicy
{
    use HandlesAuthorization;

    protected function allowedRoles(): array
    {
        return Role::values();
    }

    /**
     * Determine if the user can view any salespeople.
     * Comercial cannot list salespeople (options returns only their own record).
     */
    public function viewAny(User $user): bool
    {
        if ($user->hasRole(Role::Comercial->value)) {
            return false;
        }

        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can view the salesperson.
     */
    public function view(User $user, Salesperson $salesperson): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can create salespeople.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can update the salesperson.
     */
    public function update(User $user, Salesperson $salesperson): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can delete the salesperson.
     */
    public function delete(User $user, Salesperson $salesperson): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can restore the salesperson.
     */
    public function restore(User $user, Salesperson $salesperson): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can permanently delete the salesperson.
     */
    public function forceDelete(User $user, Salesperson $salesperson): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }
}
