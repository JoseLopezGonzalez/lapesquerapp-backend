<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Box;
use App\Models\User;

class BoxPolicy
{
    protected function allowedRoles(): array
    {
        return Role::values();
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function view(User $user, Box $box): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function update(User $user, Box $box): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function delete(User $user, Box $box): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }
}
