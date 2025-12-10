<?php

namespace App\Http\Resources\v2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RawMaterialReceptionProductResource extends JsonResource
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
            'product' => $this->whenLoaded('product', function () {
                return new ProductResource($this->product);
            }),
            'netWeight' => $this->net_weight,
            'price' => $this->price,
            'lot' => $this->lot,
            'boxes' => $this->boxes,
            'alias' => $this->alias,
        ];
    }
}
