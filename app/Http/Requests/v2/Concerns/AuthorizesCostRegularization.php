<?php

namespace App\Http\Requests\v2\Concerns;

use App\Enums\Role;

trait AuthorizesCostRegularization
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole([
            Role::Administrador->value,
            Role::Tecnico->value,
        ]) ?? false;
    }
}
