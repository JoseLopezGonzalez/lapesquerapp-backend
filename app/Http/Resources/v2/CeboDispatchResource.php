<?php

namespace App\Http\Resources\v2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CeboDispatchResource extends JsonResource
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
            'supplier' => $this->whenLoaded('supplier', fn () => new SupplierResource($this->supplier)),
            'date' => $this->date,
            'notes' => $this->notes,
            'exportType' => $this->export_type,
            'netWeight' => $this->netWeight,
            'details' => $this->whenLoaded('products', fn () => CeboDispatchProductResource::collection($this->products)),
        ];
    }
}
