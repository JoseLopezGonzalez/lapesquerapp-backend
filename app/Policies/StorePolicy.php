<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class StorePolicy
{
    use HandlesAuthorization;

    protected function allowedRoles(): array
    {
        return Role::values();
    }

    /**
     * Determine if the user can view any stores.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can view the store.
     */
    public function view(User $user, Store $store): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can create stores.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can update the store.
     */
    public function update(User $user, Store $store): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can delete the store.
     */
    public function delete(User $user, Store $store): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }
}
