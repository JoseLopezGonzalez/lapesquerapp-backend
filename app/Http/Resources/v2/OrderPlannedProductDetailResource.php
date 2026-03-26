<?php

namespace App\Http\Resources\v2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderPlannedProductDetailResource extends JsonResource
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
            'product' => $this->relationLoaded('product') ? $this->product?->toArrayAssoc() : null,
            'quantity' => $this->quantity,
            'boxes' => $this->boxes,
            'tax' => $this->relationLoaded('tax') ? $this->tax?->toArrayAssoc() : null,
            'orderId' => $this->order_id,
            'unitPrice' => $this->unit_price,
        ];
    }
}
