<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProductCategoryPolicy
{
    use HandlesAuthorization;

    protected function allowedRoles(): array
    {
        return Role::values();
    }

    /**
     * Determine if the user can view any product categories.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can view the product category.
     */
    public function view(User $user, ProductCategory $productCategory): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can create product categories.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can update the product category.
     */
    public function update(User $user, ProductCategory $productCategory): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can delete the product category.
     */
    public function delete(User $user, ProductCategory $productCategory): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }
}
