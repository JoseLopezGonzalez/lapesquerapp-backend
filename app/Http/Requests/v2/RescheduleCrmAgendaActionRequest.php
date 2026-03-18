<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class RescheduleCrmAgendaActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'nextActionAt' => 'required|date',
            'nextActionNote' => 'nullable|string|max:255',
            'sourceInteractionId' => 'nullable|integer|exists:tenant.commercial_interactions,id',
        ];
    }
}

