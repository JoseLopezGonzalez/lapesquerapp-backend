<?php

namespace App\Http\Resources\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PalletResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'observations' => $this->observations,
            'state' => $this->stateArray,
            'boxes' => $this->boxes->map(function ($box) {
                return $box->toArrayAssoc();
            }),
            'netWeight' => $this->netWeight,
            'position' => $this->position,
            'store' => $this->storeId,
            'orderId' => $this->order_id,
        ];
    }
}
