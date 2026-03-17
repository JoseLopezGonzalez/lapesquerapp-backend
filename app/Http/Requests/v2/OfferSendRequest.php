<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class OfferSendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channel' => 'required|string|in:email,pdf,whatsapp_text',
            'email' => 'nullable|email:rfc|max:255',
            'subject' => 'nullable|string|max:255',
        ];
    }
}
