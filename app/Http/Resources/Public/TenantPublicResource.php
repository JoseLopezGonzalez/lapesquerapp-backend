<?php

namespace App\Http\Resources\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantPublicResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * Respuesta pública para el endpoint de resolución de tenant por subdominio.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'status' => $this->status,
            'branding_image_url' => $this->branding_image_url,
        ];
    }
}
