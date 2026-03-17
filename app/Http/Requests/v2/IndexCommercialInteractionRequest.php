<?php

namespace App\Http\Requests\v2;

use App\Models\CommercialInteraction;
use Illuminate\Foundation\Http\FormRequest;

class IndexCommercialInteractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', CommercialInteraction::class);
    }

    public function rules(): array
    {
        return [
            'prospectId' => 'sometimes|integer|exists:tenant.prospects,id',
            'customerId' => 'sometimes|integer|exists:tenant.customers,id',
            'result' => 'sometimes|array',
            'result.*' => 'string|in:interested,no_response,not_interested,pending',
            'type' => 'sometimes|array',
            'type.*' => 'string|in:call,email,whatsapp,visit,other',
            'dateFrom' => 'sometimes|date',
            'dateTo' => 'sometimes|date',
            'perPage' => 'sometimes|integer|min:1|max:100',
        ];
    }
}
