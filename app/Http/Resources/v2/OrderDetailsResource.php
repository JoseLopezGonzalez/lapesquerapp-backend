<?php

namespace App\Http\Resources\v2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderDetailsResource extends JsonResource
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
            'buyerReference' => $this->buyer_reference,
            'customer' => $this->relationLoaded('customer') ? $this->customer?->toArrayAssoc() : null,
            'paymentTerm' => $this->relationLoaded('payment_term') ? $this->payment_term?->toArrayAssoc() : null,
            'billingAddress' => $this->billing_address,
            'shippingAddress' => $this->shipping_address,
            'transportationNotes' => $this->transportation_notes,
            'productionNotes' => $this->production_notes,
            'accountingNotes' => $this->accounting_notes,
            'salesperson' => $this->relationLoaded('salesperson') ? $this->salesperson?->toArrayAssoc() : null,
            'fieldOperator' => $this->relationLoaded('fieldOperator') ? $this->fieldOperator?->toArrayAssoc() : null,
            'fieldOperatorId' => $this->field_operator_id,
            'transport' => $this->relationLoaded('transport') ? $this->transport?->toArrayAssoc() : null,
            'entryDate' => $this->entry_date,
            'loadDate' => $this->load_date,
            'status' => $this->status,
            'pallets' => $this->relationLoaded('pallets')
                ? $this->pallets->map(function ($pallet) {
                    return $pallet->toArrayAssocV2();
                })
                : [],
            'incoterm' => $this->relationLoaded('incoterm') ? $this->incoterm?->toArrayAssoc() : null,
            'totalNetWeight' => $this->totalNetWeight,
            'numberOfPallets' => $this->numberOfPallets,
            'totalBoxes' => $this->totalBoxes,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'plannedProductDetails' => $this->relationLoaded('plannedProductDetails')
                ? $this->plannedProductDetails->map(function ($detail) {
                    return $detail->toArrayAssoc();
                })
                : null,
            'productionProductDetails' => $this->productionProductDetails,
            'productDetails' => $this->productDetails,
            'subTotalAmount' => $this->subTotalAmount,
            'totalAmount' => $this->totalAmount,
            'totalCost' => $this->totalCost,
            'grossMargin' => $this->grossMargin,
            'marginPercentage' => $this->marginPercentage,
            'emails' => $this->emailsArray,
            'ccEmails' => $this->ccEmailsArray,
            'truckPlate' => $this->truck_plate,
            'trailerPlate' => $this->trailer_plate,
            'temperature' => $this->temperature,
            'incident' => $this->relationLoaded('incident') ? $this->incident?->toArrayAssoc() : null,
            'offerId' => $this->relationLoaded('offer') ? $this->offer?->id : null,
            'routeId' => $this->route_id,
            'routeStopId' => $this->route_stop_id,
            'route' => $this->relationLoaded('route') && $this->route ? [
                'id' => $this->route->id,
                'name' => $this->route->name,
                'routeDate' => $this->route->route_date,
            ] : null,
            'routeStop' => $this->relationLoaded('routeStop') && $this->routeStop ? [
                'id' => $this->routeStop->id,
                'position' => $this->routeStop->position,
                'stopType' => $this->routeStop->stop_type,
                'targetType' => $this->routeStop->target_type,
                'label' => $this->routeStop->label,
                'address' => $this->routeStop->address,
                'status' => $this->routeStop->status,
                'resultType' => $this->routeStop->result_type,
                'resultNotes' => $this->routeStop->result_notes,
            ] : null,
            'createdByUserId' => $this->created_by_user_id,
        ];
    }
}
