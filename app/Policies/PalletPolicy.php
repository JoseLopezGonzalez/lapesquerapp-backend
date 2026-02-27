<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Pallet;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PalletPolicy
{
    use HandlesAuthorization;

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
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can view the pallet.
     */
    public function view(User $user, Pallet $pallet): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can create pallets.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can update the pallet.
     */
    public function update(User $user, Pallet $pallet): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can delete the pallet.
     */
    public function delete(User $user, Pallet $pallet): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can clear the pallet timeline (historial). Solo administrador y técnico.
     */
    public function clearTimeline(User $user, Pallet $pallet): bool
    {
        return $user->hasAnyRole($this->rolesCanClearTimeline());
    }
}
