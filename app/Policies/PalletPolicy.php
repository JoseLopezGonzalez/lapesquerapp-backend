<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\ExternalUser;
use App\Models\Pallet;
use App\Models\User;
use App\Services\ActorScopeService;
use Illuminate\Auth\Access\HandlesAuthorization;

class PalletPolicy
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
     * Roles que pueden borrar el historial (timeline) del palet. Solo administrador y técnico.
     */
    protected function rolesCanClearTimeline(): array
    {
        return [
            Role::Administrador->value,
            Role::Tecnico->value,
        ];
    }

    /**
     * Determine if the user can view any pallets.
     */
    public function viewAny(User|ExternalUser $user): bool
    {
        if ($user instanceof ExternalUser) {
            return $user->is_active && $user->stores()->exists();
        }

        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can view the pallet.
     */
    public function view(User|ExternalUser $user, Pallet $pallet): bool
    {
        if ($user instanceof ExternalUser) {
            return $user->is_active && $this->scope->canAccessStoreId($user, $pallet->store_id);
        }

        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can create pallets.
     */
    public function create(User|ExternalUser $user): bool
    {
        if ($user instanceof ExternalUser) {
            return $user->is_active;
        }

        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can update the pallet.
     */
    public function update(User|ExternalUser $user, Pallet $pallet): bool
    {
        if ($user instanceof ExternalUser) {
            return $user->is_active && $this->scope->canAccessStoreId($user, $pallet->store_id);
        }

        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can delete the pallet.
     */
    public function delete(User|ExternalUser $user, Pallet $pallet): bool
    {
        if ($user instanceof ExternalUser) {
            return false;
        }

        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can clear the pallet timeline (historial). Solo administrador y técnico.
     */
    public function clearTimeline(User|ExternalUser $user, Pallet $pallet): bool
    {
        if ($user instanceof ExternalUser) {
            return false;
        }

        return $user->hasAnyRole($this->rolesCanClearTimeline());
    }
}
