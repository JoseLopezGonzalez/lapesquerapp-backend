<?php

namespace App\Http\Resources\v2;

use App\Support\PalletManualCostPolicy;
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
            'storeType' => $this->store_type,
            'externalUser' => $this->relationLoaded('externalUser') && $this->externalUser ? [
                'id' => $this->externalUser->id,
                'name' => $this->externalUser->name,
                'email' => $this->externalUser->email,
                'type' => $this->externalUser->type,
            ] : null,
            'netWeightPallets' => $this->netWeightPallets,
            'totalNetWeight' => $this->totalNetWeight,
            'content' => [
                'pallets' => $this->relationLoaded('palletsV2') ? $this->palletsV2->map(function ($pallet) use ($request) {
                    $assoc = $pallet->toArrayAssocV2();
                    if (! PalletManualCostPolicy::authorized($request->user())) {
                        return PalletManualCostPolicy::stripFromPalletAssocArray($assoc);
                    }

                    return $assoc;
                }) : [],
                'boxes' => [],
                'bigBoxes' => [],
            ],
            'map' => json_decode($this->map, true),
        ];
    }
}
