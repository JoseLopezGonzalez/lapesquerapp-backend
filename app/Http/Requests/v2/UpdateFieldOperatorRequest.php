<?php

namespace App\Http\Requests\v2;

use App\Enums\Role;
use App\Models\FieldOperator;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFieldOperatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $fieldOperator = $this->route('field_operator') ?? $this->route('fieldOperator');
        if (! $fieldOperator instanceof FieldOperator) {
            $fieldOperator = FieldOperator::find($fieldOperator);
        }

        return ! $fieldOperator || $this->user()->can('update', $fieldOperator);
    }

    public function rules(): array
    {
        $emailRule = app()->environment('testing') ? 'string|email:rfc|distinct' : 'string|email:rfc,dns|distinct';
        $fieldOperator = $this->route('field_operator') ?? $this->route('fieldOperator');
        $fieldOperatorId = $fieldOperator instanceof FieldOperator ? $fieldOperator->id : $fieldOperator;

        return [
            'name' => 'sometimes|string|max:255',
            'emails' => 'sometimes|nullable|array',
            'emails.*' => $emailRule,
            'ccEmails' => 'sometimes|nullable|array',
            'ccEmails.*' => $emailRule,
            'user_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:tenant.users,id',
                Rule::unique(FieldOperator::class, 'user_id')->ignore($fieldOperatorId),
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! $value) {
                        return;
                    }

                    $user = User::find($value);
                    if (! $user || $user->role !== Role::RepartidorAutoventa->value) {
                        $fail('El usuario enlazado debe tener rol repartidor/autoventa.');
                        return;
                    }

                    if ($user->salesperson()->exists()) {
                        $fail('No se puede enlazar como actor operativo a un usuario que ya tiene identidad comercial CRM.');
                    }
                },
            ],
        ];
    }
}
