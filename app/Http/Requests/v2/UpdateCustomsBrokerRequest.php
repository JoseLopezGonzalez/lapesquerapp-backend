<?php

namespace App\Http\Requests\v2;

use App\Models\CustomsBroker;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomsBrokerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $id = $this->route('customsBroker');
        if (! $id) {
            return false;
        }

        return $this->user()->can('update', CustomsBroker::findOrFail($id));
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|min:3|max:255',
            'address' => 'nullable|string|max:1000',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
        ];
    }
}
