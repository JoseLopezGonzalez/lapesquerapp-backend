<?php

namespace App\Http\Requests\v2;

use App\Models\ExternalUser;
use Illuminate\Foundation\Http\FormRequest;

class IndexExternalUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', ExternalUser::class);
    }

    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|string|max:255',
            'type' => 'nullable|string|in:maquilador',
            'is_active' => 'nullable|boolean',
            'perPage' => 'nullable|integer|min:1|max:100',
        ];
    }
}
