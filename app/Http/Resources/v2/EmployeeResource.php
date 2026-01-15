<?php

namespace App\Http\Resources\v2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
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
            'nfcUid' => $this->nfc_uid,
            'lastPunchEvent' => $this->whenLoaded('lastPunchEvent', function () {
                return $this->lastPunchEvent ? [
                    'event_type' => $this->lastPunchEvent->event_type,
                    'timestamp' => $this->lastPunchEvent->timestamp->format('Y-m-d H:i:s'),
                ] : null;
            }),
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}

