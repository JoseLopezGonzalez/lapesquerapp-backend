<?php

namespace App\Http\Resources\v2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SessionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        $tokenable = $this->relationLoaded('tokenable') ? $this->tokenable : null;

        return [
            'id' => $this->id,
            'user_id' => $this->tokenable_id,
            'user_name' => $tokenable?->name ?? 'Desconocido',
            'email' => $tokenable?->email ?? 'Desconocido',
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}