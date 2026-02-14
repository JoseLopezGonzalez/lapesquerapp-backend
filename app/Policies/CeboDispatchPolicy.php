<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\CeboDispatch;
use App\Models\User;

class CeboDispatchPolicy
{
    protected function allowedRoles(): array
    {
        return Role::values();
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function view(User $user, CeboDispatch $ceboDispatch): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function update(User $user, CeboDispatch $ceboDispatch): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function delete(User $user, CeboDispatch $ceboDispatch): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }
}
