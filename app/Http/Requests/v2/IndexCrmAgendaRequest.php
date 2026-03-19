<?php

namespace App\Http\Requests\v2;

use App\Models\AgendaAction;
use Illuminate\Foundation\Http\FormRequest;

class IndexCrmAgendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'startDate' => 'sometimes|date',
            'endDate' => 'sometimes|date',
            'targetType' => 'sometimes|in:prospect,customer',
            'status' => 'sometimes|array',
            'status.*' => 'string|in:pending,reprogrammed,done,cancelled',
        ];
    }
}

