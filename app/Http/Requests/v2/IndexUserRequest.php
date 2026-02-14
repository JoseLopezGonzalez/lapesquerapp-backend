<?php

namespace App\Http\Requests\v2;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class IndexUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', User::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => 'nullable|string',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|string|max:255',
            'role' => 'nullable|string|max:50',
            'created_at' => 'nullable|array',
            'created_at.start' => 'nullable|string',
            'created_at.end' => 'nullable|string',
            'sort' => 'nullable|string|in:name,email,role,created_at',
            'direction' => 'nullable|string|in:asc,desc',
            'perPage' => 'nullable|integer|min:1|max:100',
        ];
    }
}
