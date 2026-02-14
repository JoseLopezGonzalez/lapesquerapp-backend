<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\ProductFamily;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProductFamilyPolicy
{
    use HandlesAuthorization;

    protected function allowedRoles(): array
    {
        return Role::values();
    }

    /**
     * Determine if the user can view any product families.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can view the product family.
     */
    public function view(User $user, ProductFamily $productFamily): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can create product families.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can update the product family.
     */
    public function update(User $user, ProductFamily $productFamily): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can delete the product family.
     */
    public function delete(User $user, ProductFamily $productFamily): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }
}
