<?php

namespace App\Http\Resources\v2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RawMaterialReceptionResource extends JsonResource
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
            'supplier' => $this->whenLoaded('supplier', function () {
                return new SupplierResource($this->supplier);
            }),
            'date' => $this->date,
            'notes' => $this->notes,
            'creationMode' => $this->creation_mode, // 'lines' o 'pallets'
            'netWeight' => $this->netWeight,
            'species' => $this->species,
            'details' => RawMaterialReceptionProductResource::collection($this->products),
            'pallets' => \App\Http\Resources\v2\PalletResource::collection($this->pallets),
            'totalAmount' => $this->totalAmount,
            'canEdit' => $this->can_edit,
            'cannotEditReason' => $this->cannot_edit_reason,
        ];
    }


    
}
