<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Label;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LabelPolicy
{
    use HandlesAuthorization;

    protected function allowedRoles(): array
    {
        return Role::values();
    }

    /**
     * Determine if the user can view any labels.
     */
    public function viewAny(User $user): bool
    {
        if ($user->hasRole(Role::Comercial->value)) {
            return false;
        }
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can view the label.
     */
    public function view(User $user, Label $label): bool
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

    /**
     * Determine if the user can create labels.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can update the label.
     */
    public function update(User $user, Label $label): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can delete the label.
     */
    public function delete(User $user, Label $label): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }
}
