<?php

namespace App\Http\Resources\v2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryRouteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'routeTemplateId' => $this->route_template_id,
            'name' => $this->name,
            'description' => $this->description,
            'routeDate' => $this->route_date,
            'status' => $this->status,
            'salesperson' => $this->relationLoaded('salesperson') ? $this->salesperson?->toArrayAssoc() : null,
            'fieldOperator' => $this->relationLoaded('fieldOperator') ? $this->fieldOperator?->toArrayAssoc() : null,
            'createdByUserId' => $this->created_by_user_id,
            'stops' => $this->whenLoaded('stops', fn () => $this->stops->map(fn ($stop) => [
                'id' => $stop->id,
                'position' => $stop->position,
                'stopType' => $stop->stop_type,
                'targetType' => $stop->target_type,
                'customerId' => $stop->customer_id,
                'prospectId' => $stop->prospect_id,
                'label' => $stop->label,
                'address' => $stop->address,
                'notes' => $stop->notes,
                'status' => $stop->status,
                'resultType' => $stop->result_type,
                'resultNotes' => $stop->result_notes,
                'completedAt' => $stop->completed_at,
            ])->values()),
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
