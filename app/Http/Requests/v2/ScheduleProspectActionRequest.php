<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleProspectActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nextActionAt' => 'required|date',
        ];
    }
}
