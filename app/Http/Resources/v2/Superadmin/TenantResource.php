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
            'admin_email' => $this->admin_email,

            'onboarding' => [
                'step' => $this->onboarding_step,
                'total_steps' => \App\Services\Superadmin\TenantOnboardingService::TOTAL_STEPS,
                'step_label' => $this->onboarding_step_label,
                'status' => $this->onboarding_status,
                'error' => $this->onboarding_error,
                'failed_at' => $this->onboarding_failed_at,
            ],

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
