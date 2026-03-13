<?php

namespace App\Http\Requests\v2;

use App\Models\ExternalUser;
use App\Services\AuthActorService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExternalUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ExternalUser::class);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'company_name' => 'sometimes|nullable|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique(ExternalUser::class, 'email'),
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (app(AuthActorService::class)->emailExistsOnOtherActor($value, ExternalUser::class)) {
                        $fail('El email ya está en uso por un usuario interno del tenant.');
                    }
                },
            ],
            'type' => ['sometimes', 'string', Rule::in([ExternalUser::TYPE_MAQUILADOR])],
            'is_active' => 'sometimes|boolean',
            'notes' => 'sometimes|nullable|string|max:2000',
        ];
    }
}
