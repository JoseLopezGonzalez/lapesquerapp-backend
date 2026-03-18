<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class IndexCrmAgendaSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'limitNext' => 'sometimes|integer|min:1|max:100',
        ];
    }
}

