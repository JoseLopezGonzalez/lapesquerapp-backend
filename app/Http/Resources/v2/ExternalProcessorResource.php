<?php

namespace App\Http\Resources\v2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExternalProcessorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'legalName' => $this->legal_name,
            'vatNumber' => $this->vat_number,
            'sanitaryRegistrationNumber' => $this->sanitary_registration_number,
            'contactPerson' => $this->contact_person,
            'phone' => $this->phone,
            'emails' => $this->emailsArray,
            'ccEmails' => $this->ccEmailsArray,
            'address' => $this->address,
            'city' => $this->city,
            'postalCode' => $this->postal_code,
            'province' => $this->province,
            'country' => $this->whenLoaded('country', fn () => $this->country ? [
                'id' => $this->country->id,
                'name' => $this->country->name,
            ] : null),
            'isActive' => $this->is_active,
            'notes' => $this->notes,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
