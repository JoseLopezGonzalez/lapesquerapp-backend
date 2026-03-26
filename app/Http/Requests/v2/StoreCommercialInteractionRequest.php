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
            $hasAgendaActionId = $this->filled('agendaActionId');
            $hasNextActionPayload = $this->filled('nextActionAt') || $this->filled('nextActionNote');

            // Flujo 2 pasos: sin agendaActionId no se admiten campos de próxima acción
            // en el endpoint de interacción.
            if (! $hasAgendaActionId && $hasNextActionPayload) {
                $validator->errors()->add(
                    'nextActionAt',
                    'No se puede gestionar próxima acción en este paso. Registra la interacción y usa resolve-next-action.'
                );
            }

            // Compat legacy: nextActionNote solo tiene sentido si llega nextActionAt.
            if ($hasAgendaActionId && ! $this->filled('nextActionAt') && $this->filled('nextActionNote')) {
                $validator->errors()->add(
                    'nextActionNote',
                    'nextActionNote requiere nextActionAt.'
                );
            }
        });
    }
}
