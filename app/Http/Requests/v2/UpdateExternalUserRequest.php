<?php

namespace App\Http\Requests\v2;

use App\Models\ExternalUser;
use App\Services\AuthActorService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExternalUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $externalUser = $this->route('externalUser');
        $model = $externalUser instanceof ExternalUser ? $externalUser : ExternalUser::find($externalUser);

        return $model ? $this->user()->can('update', $model) : true;
    }

    public function rules(): array
    {
        $externalUser = $this->route('externalUser');
        $id = $externalUser instanceof ExternalUser ? $externalUser->id : $externalUser;

        return [
            'name' => 'sometimes|string|max:255',
            'company_name' => 'sometimes|nullable|string|max:255',
            'email' => [
                'sometimes',
                'email',
                Rule::unique(ExternalUser::class, 'email')->ignore($id),
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
