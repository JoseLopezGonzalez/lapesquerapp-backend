<?php

namespace App\Http\Requests\v2;

use App\Enums\Role;
use App\Models\User;
use App\Services\AuthActorService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique(User::class, 'email')->whereNull('deleted_at'),
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (app(AuthActorService::class)->emailExistsOnOtherActor($value, User::class)) {
                        $fail('El email ya está en uso por un usuario externo del tenant.');
                    }
                },
            ],
            'role' => ['required', 'string', Rule::in(Role::values())],
            'active' => 'sometimes|boolean',
        ];
    }
}
