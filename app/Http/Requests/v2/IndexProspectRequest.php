<?php

namespace App\Http\Requests\v2;

use App\Models\Prospect;
use Illuminate\Foundation\Http\FormRequest;

class IndexProspectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Prospect::class);
    }

    public function rules(): array
    {
        return [
            'search' => 'sometimes|string|max:255',
            'status' => 'sometimes|array',
            'status.*' => 'string|in:new,following,offer_sent,customer,discarded',
            'origin' => 'sometimes|array',
            'origin.*' => 'string|in:conxemar,direct,referral,web,other',
            'countries' => 'sometimes|array',
            'countries.*' => 'integer',
            'salespeople' => 'sometimes|array',
            'salespeople.*' => 'integer',
            'perPage' => 'sometimes|integer|min:1|max:100',
        ];
    }
}
