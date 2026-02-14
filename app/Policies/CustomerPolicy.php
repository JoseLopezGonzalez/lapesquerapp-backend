<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    protected function allowedRoles(): array
    {
        return Role::values();
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function restore(User $user, Customer $customer): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function forceDelete(User $user, Customer $customer): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }
}
