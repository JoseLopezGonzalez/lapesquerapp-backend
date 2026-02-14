<?php

namespace App\Http\Requests\v2;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $param = $this->route('user');
        $user = $param instanceof User ? $param : User::find($param);
        if (!$user) {
            return true;
        }
        return $this->user()->can('update', $user);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $userId = $user instanceof User ? $user->id : $user;

        return [
            'name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'email',
                Rule::unique(User::class, 'email')->whereNull('deleted_at')->ignore($userId),
            ],
            'active' => 'sometimes|boolean',
            'role' => ['sometimes', 'string', Rule::in(Role::values())],
        ];
    }
}
