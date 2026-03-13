<?php

namespace App\Http\Resources\v2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExternalUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'companyName' => $this->company_name,
            'email' => $this->email,
            'type' => $this->type,
            'isActive' => $this->is_active,
            'notes' => $this->notes,
            'storesCount' => $this->when(isset($this->stores_count), $this->stores_count),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
