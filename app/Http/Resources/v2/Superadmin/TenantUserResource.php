<?php

namespace App\Http\Resources\v2\Superadmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'active' => (bool) $this->active,
            'last_login_at' => $this->last_login_at,
        ];
    }
}
