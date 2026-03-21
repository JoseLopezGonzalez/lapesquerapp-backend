<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\ExternalUser;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProductPolicy
{
    use HandlesAuthorization;

    protected function allowedRoles(): array
    {
        return Role::values();
    }

    /**
     * Determine if the user can view any products.
     */
    public function viewAny(User|ExternalUser $user): bool
    {
        if ($user instanceof ExternalUser) {
            return false;
        }

        if ($user->hasRole(Role::Comercial->value) || $user->hasRole(Role::RepartidorAutoventa->value)) {
            return false;
        }
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can view the product.
     */
    public function view(User|ExternalUser $user, Product $product): bool
    {
        if ($user instanceof ExternalUser) {
            return false;
        }

        if ($user->hasRole(Role::Comercial->value) || $user->hasRole(Role::RepartidorAutoventa->value)) {
            return false;
        }
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function viewOptions(User|ExternalUser $user): bool
    {
        if ($user instanceof ExternalUser) {
            return $user->is_active;
        }

        return ! $user->hasRole(Role::RepartidorAutoventa->value);
    }

    public function viewOperationalOptions(User|ExternalUser $user): bool
    {
        if ($user instanceof ExternalUser) {
            return false;
        }

        return $user->hasRole(Role::RepartidorAutoventa->value) && $user->fieldOperator !== null;
    }

    /**
     * Determine if the user can create products.
     */
    public function create(User $user): bool
    {
        if ($user->hasRole(Role::RepartidorAutoventa->value)) {
            return false;
        }
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can update the product.
     */
    public function update(User $user, Product $product): bool
    {
        if ($user->hasRole(Role::RepartidorAutoventa->value)) {
            return false;
        }
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can delete the product.
     */
    public function delete(User $user, Product $product): bool
    {
        if ($user->hasRole(Role::RepartidorAutoventa->value)) {
            return false;
        }
        return $user->hasAnyRole($this->allowedRoles());
    }
}
