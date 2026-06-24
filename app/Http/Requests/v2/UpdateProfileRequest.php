<?php

namespace App\Http\Requests\v2;

use App\Models\User;
use App\Services\AuthActorService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'email',
                Rule::unique(User::class, 'email')->whereNull('deleted_at')->ignore($userId),
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (app(AuthActorService::class)->emailExistsOnOtherActor($value, User::class)) {
                        $fail('El email ya está en uso por otro actor del tenant.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'El nombre no puede superar los 255 caracteres.',
            'email.email' => 'El email no tiene un formato válido.',
            'email.unique' => 'El email ya está en uso.',
        ];
    }
}
