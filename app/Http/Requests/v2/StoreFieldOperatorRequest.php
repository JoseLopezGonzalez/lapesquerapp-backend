<?php

namespace App\Http\Requests\v2;

use App\Enums\Role;
use App\Models\FieldOperator;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFieldOperatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', FieldOperator::class);
    }

    public function rules(): array
    {
        $emailRule = app()->environment('testing') ? 'string|email:rfc|distinct' : 'string|email:rfc,dns|distinct';

        return [
            'name' => 'required|string|max:255',
            'emails' => 'nullable|array',
            'emails.*' => $emailRule,
            'ccEmails' => 'nullable|array',
            'ccEmails.*' => $emailRule,
            'user_id' => [
                'nullable',
                'integer',
                'exists:tenant.users,id',
                Rule::unique(FieldOperator::class, 'user_id'),
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
