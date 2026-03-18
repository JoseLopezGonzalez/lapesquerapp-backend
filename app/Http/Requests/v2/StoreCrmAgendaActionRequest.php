<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class StoreCrmAgendaActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'targetType' => 'required|string|in:prospect,customer',
            'targetId' => 'required|integer',
            'nextActionAt' => 'required|date',
            'nextActionNote' => 'nullable|string|max:255',
            'sourceInteractionId' => 'nullable|integer|exists:tenant.commercial_interactions,id',
            'previousActionId' => 'nullable|integer|exists:tenant.agenda_actions,id',
        ];
    }
}

