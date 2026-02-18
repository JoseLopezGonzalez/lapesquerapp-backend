<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\PaymentTerm;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaymentTermPolicy
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

    public function view(User $user, PaymentTerm $paymentTerm): bool
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

    public function create(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function update(User $user, PaymentTerm $paymentTerm): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function delete(User $user, PaymentTerm $paymentTerm): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }
}
