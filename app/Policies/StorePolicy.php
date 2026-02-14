<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Store;
use App\Models\User;

class StorePolicy
{
    protected function allowedRoles(): array
    {
        return Role::values();
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function view(User $user, Store $store): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function update(User $user, Store $store): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function delete(User $user, Store $store): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }
}
