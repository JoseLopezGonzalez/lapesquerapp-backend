<?php

namespace App\Http\Requests\v2;

use App\Models\CustomsBroker;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomsBrokerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CustomsBroker::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|min:3|max:255',
            'address' => 'nullable|string|max:1000',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
        ];
    }
}
