<?php

namespace App\Http\Resources\v2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionInputResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $box = $this->relationLoaded('box') ? $this->box : null;
        $boxProduct = $box && $box->relationLoaded('product') ? $box->product : null;
        $pallet = $this->relationLoaded('pallet') ? $this->pallet : null;

        return [
            'id' => $this->id,
            'productionRecordId' => $this->production_record_id,
            'boxId' => $this->box_id,
            'box' => $this->whenLoaded('box', function () {
                return new BoxResource($this->box);
            }),
            // Datos calculados desde la caja
            'product' => $this->when($boxProduct, function () use ($boxProduct) {
                return [
                    'id' => $boxProduct->id,
                    'name' => $boxProduct->name,
                ];
            }),
            'lot' => $this->lot,
            'weight' => $this->weight,
            'pallet' => $this->when($pallet, function () use ($pallet) {
                return [
                    'id' => $pallet->id,
                ];
            }),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
