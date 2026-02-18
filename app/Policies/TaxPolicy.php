<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TaxPolicy
{
    use HandlesAuthorization;

    protected function allowedRoles(): array
    {
        return Role::values();
    }

    public function viewAny(User $user): bool
    {
        if ($user->hasRole(Role::Comercial->value)) {
            return false;
        }
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function viewOptions(User $user): bool
    {
        return true;
    }
}
