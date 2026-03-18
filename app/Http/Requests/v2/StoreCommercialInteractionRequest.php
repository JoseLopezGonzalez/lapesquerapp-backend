<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommercialInteractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prospectId' => 'nullable|integer|exists:tenant.prospects,id',
            'customerId' => 'nullable|integer|exists:tenant.customers,id',
            'type' => 'required|string|in:call,email,whatsapp,visit,other',
            'occurredAt' => 'required|date',
            'summary' => 'required|string|max:500',
            'result' => 'required|string|in:interested,no_response,not_interested,pending',
            'nextActionNote' => 'nullable|string|max:255',
            'nextActionAt' => 'nullable|date',
            'agendaActionId' => 'nullable|integer|exists:tenant.agenda_actions,id',
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function () use ($validator) {
            // Regla V1: “done” en agenda requiere ligadura explícita (agendaActionId)
            // cuando no se programa una nueva próxima acción.
            if (! $this->filled('nextActionAt') && ! $this->filled('agendaActionId')) {
                $validator->errors()->add('agendaActionId', 'agendaActionId es requerida cuando no se envía nextActionAt.');
            }
        });
    }
}
