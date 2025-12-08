<?php

namespace App\Http\Resources\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreDetailsResource extends JsonResource
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
            'name' => $this->name,
            'temperature' => $this->temperature,
            'capacity' => $this->capacity,
            'netWeightPallets' => $this->netWeightPallets,
            'totalNetWeight' => $this->totalNetWeight,
            'content' => [
                'pallets' => $this->palletsV2->map(function ($pallet) {
                    return $pallet->toArrayAssocV2();
                }),
                'boxes' => [],
                'bigBoxes' => [],
            ],
            'map' => json_decode($this->map, true),
        ];
    }
}
