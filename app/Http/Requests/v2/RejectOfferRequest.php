<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class RejectOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string',
        ];
    }
}
