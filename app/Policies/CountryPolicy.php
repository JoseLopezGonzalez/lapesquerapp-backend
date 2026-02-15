<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Country;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CountryPolicy
{
    use HandlesAuthorization;

    protected function allowedRoles(): array
    {
        return Role::values();
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function view(User $user, Country $country): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function update(User $user, Country $country): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function delete(User $user, Country $country): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }
}
