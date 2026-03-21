<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OfferPolicy
{
    use HandlesAuthorization;

    protected function allowedRoles(): array
    {
        return [
            Role::Tecnico->value,
            Role::Administrador->value,
            Role::Direccion->value,
            Role::Comercial->value,
        ];
    }

    public function viewAny(User $user): bool
    {
        if ($user->hasRole(Role::RepartidorAutoventa->value)) {
            return false;
        }
        if ($user->hasRole(Role::Comercial->value)) {
            return $user->salesperson !== null;
        }

        return $user->hasAnyRole($this->allowedRoles());
    }

    public function view(User $user, Offer $offer): bool
    {
        if ($user->hasRole(Role::RepartidorAutoventa->value)) {
            return false;
        }
        if ($user->hasRole(Role::Comercial->value)) {
            return $user->salesperson && $offer->salesperson_id === $user->salesperson->id;
        }

        return $user->hasAnyRole($this->allowedRoles());
    }

    public function create(User $user): bool
    {
        if ($user->hasRole(Role::RepartidorAutoventa->value)) {
            return false;
        }
        if ($user->hasRole(Role::Comercial->value)) {
            return $user->salesperson !== null;
        }

        return $user->hasAnyRole($this->allowedRoles());
    }

    public function update(User $user, Offer $offer): bool
    {
        return $this->view($user, $offer);
    }

    public function delete(User $user, Offer $offer): bool
    {
        return $this->view($user, $offer);
    }
}
