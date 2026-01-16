<?php

namespace App\Http\Resources\v2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
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
            /* id Primaria	
	2	user_id 
	3	ip_address	
	4	device	
	5	browser		
	6	location		
	7	created_at	
	8	updated_at	
	9	country		
	10	city		
	11	region		
	12	platform		
	13	path		
	14	method */
            'user' => $this->user?->toArrayAssoc(),
            'ipAddress' => $this->ip_address,
            'device' => $this->device,
            'browser' => $this->browser,
            'location' => $this->location,
            'country' => $this->country,
            'city' => $this->city,
            'region' => $this->region,
            'platform' => $this->platform,
            'path' => $this->path,
            'method' => $this->method,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
