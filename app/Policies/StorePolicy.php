<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\ExternalUser;
use App\Models\Store;
use App\Models\User;
use App\Services\ActorScopeService;
use Illuminate\Auth\Access\HandlesAuthorization;

class StorePolicy
{
    use HandlesAuthorization;

    public function __construct(
        protected ActorScopeService $scope
    ) {}

    protected function allowedRoles(): array
    {
        return Role::values();
    }

    /**
     * Determine if the user can view any stores.
     */
    public function viewAny(User|ExternalUser $user): bool
    {
        if ($user instanceof ExternalUser) {
            return $user->is_active && $user->stores()->exists();
        }
        if ($user->hasRole(Role::Comercial->value) || $user->hasRole(Role::RepartidorAutoventa->value)) {
            return false;
        }

        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can view the store.
     */
    public function view(User|ExternalUser $user, Store $store): bool
    {
        if ($user instanceof ExternalUser) {
            return $user->is_active && $this->scope->canAccessStoreId($user, $store->id);
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

    /**
     * Determine if the user can create stores.
     */
    public function create(User $user): bool
    {
        if ($user->hasRole(Role::RepartidorAutoventa->value)) {
            return false;
        }
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can update the store.
     */
    public function update(User $user, Store $store): bool
    {
        if ($user->hasRole(Role::RepartidorAutoventa->value)) {
            return false;
        }
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can delete the store.
     */
    public function delete(User $user, Store $store): bool
    {
        if ($user->hasRole(Role::RepartidorAutoventa->value)) {
            return false;
        }
        return $user->hasAnyRole($this->allowedRoles());
    }
}
