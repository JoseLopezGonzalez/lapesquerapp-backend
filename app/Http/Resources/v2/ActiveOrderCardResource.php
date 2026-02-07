<?php

namespace App\Http\Resources\v2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActiveOrderCardResource extends JsonResource
{
    /**
     * Recurso ligero para la tarjeta de pedido activo (GET orders/active).
     * Solo los campos necesarios para la UI: id, estado, cliente, fecha de carga.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'customer' => $this->when($this->relationLoaded('customer') && $this->customer, [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ], null),
            'loadDate' => $this->load_date,
        ];
    }
}
