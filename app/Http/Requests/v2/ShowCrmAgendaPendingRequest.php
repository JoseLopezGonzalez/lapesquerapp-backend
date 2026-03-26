<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class ShowCrmAgendaPendingRequest extends FormRequest
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
        ];
    }
}

