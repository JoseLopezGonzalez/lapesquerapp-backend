<?php

namespace App\Http\Resources\v2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'orderType' => $this->order_type ?? 'standard',
            'customer' => $this->customer->toArrayAssoc(),
            'buyerReference' => $this->buyer_reference,
            'status' => $this->status,
            'loadDate' => $this->load_date,
            'salesperson' => $this->salesperson->toArrayAssoc(),
            'transport' => $this->transport->toArrayAssoc(),
            'pallets' => $this->numberOfPallets,
            'totalBoxes' => $this->totalBoxes,
            'incoterm' => $this->incoterm->toArrayAssoc(),
            'totalNetWeight' => $this->totalNetWeight,
            'subtotalAmount' => $this->subtotal_amount,
            'totalAmount' => $this->total_amount,
        ];
    }
}
