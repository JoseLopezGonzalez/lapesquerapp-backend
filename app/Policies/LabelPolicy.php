<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Label;
use App\Models\User;

class LabelPolicy
{
    protected function allowedRoles(): array
    {
        return Role::values();
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function view(User $user, Label $label): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function update(User $user, Label $label): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function delete(User $user, Label $label): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }
}
