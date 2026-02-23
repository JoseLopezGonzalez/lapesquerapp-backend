<?php

namespace App\Http\Resources\v2\Superadmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'subdomain' => $this->subdomain,
            'database' => $this->database,
            'status' => $this->status,
            'plan' => $this->plan,
            'renewal_at' => $this->renewal_at?->toDateString(),
            'timezone' => $this->timezone,
            'branding_image_url' => $this->branding_image_url,
            'last_activity_at' => $this->last_activity_at,
            'onboarding_step' => $this->onboarding_step,
            'admin_email' => $this->admin_email,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
