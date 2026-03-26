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
            'customer' => $this->relationLoaded('customer') ? $this->customer?->toArrayAssoc() : null,
            'buyerReference' => $this->buyer_reference,
            'status' => $this->status,
            'loadDate' => $this->load_date,
            'salesperson' => $this->relationLoaded('salesperson') ? $this->salesperson?->toArrayAssoc() : null,
            'fieldOperator' => $this->relationLoaded('fieldOperator') ? $this->fieldOperator?->toArrayAssoc() : null,
            'fieldOperatorId' => $this->field_operator_id,
            'transport' => $this->relationLoaded('transport') ? $this->transport?->toArrayAssoc() : null,
            'pallets' => $this->numberOfPallets,
            'totalBoxes' => $this->totalBoxes,
            'incoterm' => $this->relationLoaded('incoterm') ? $this->incoterm?->toArrayAssoc() : null,
            'totalNetWeight' => $this->totalNetWeight,
            'subtotalAmount' => $this->subtotal_amount,
            'totalAmount' => $this->total_amount,
            'offerId' => $this->relationLoaded('offer') ? $this->offer?->id : null,
            'routeId' => $this->route_id,
            'routeStopId' => $this->route_stop_id,
            'createdByUserId' => $this->created_by_user_id,
        ];
    }
}
