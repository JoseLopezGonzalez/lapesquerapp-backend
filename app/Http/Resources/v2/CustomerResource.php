<?php

namespace App\Http\Resources\v2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Nota: Customer::toArrayAssoc() serializa relaciones anidadas (paymentTerm, salesperson, etc.)
        // condicionalmente vía relationLoaded(). No usar parent::toArrayAssoc() (magic __call, opaco
        // para IDE/análisis estático/generación OpenAPI): llamar explícitamente al modelo subyacente.
        return $this->resource->toArrayAssoc();
    }
}
