<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\ProductionRecord;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProductionRecordPolicy
{
    use HandlesAuthorization;

    protected function allowedRoles(): array
    {
        return Role::values();
    }

    protected function rolesCanDelete(): array
    {
        return [
            Role::Administrador->value,
            Role::Tecnico->value,
        ];
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function view(User $user, ProductionRecord $productionRecord): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function update(User $user, ProductionRecord $productionRecord): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function delete(User $user, ProductionRecord $productionRecord): bool
    {
        return $user->hasAnyRole($this->rolesCanDelete());
    }
}
