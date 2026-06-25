<?php

namespace App\Http\Requests\v2;

use App\Enums\Role;
use App\Models\User;
use App\Services\AuthActorService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $param = $this->route('user');
        $user = $param instanceof User ? $param : User::find($param);
        if (! $user) {
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
        $userId = $user instanceof User ? $user->getKey() : $user;

        return [
            'name' => 'sometimes|filled|string|max:255',
            'email' => [
                'sometimes',
                'email',
                Rule::unique(User::class, 'email')->whereNull('deleted_at')->ignore($userId),
                function (string $_attribute, mixed $value, \Closure $fail) {
                    if (app(AuthActorService::class)->emailExistsOnOtherActor($value, User::class)) {
                        $fail('El email ya está en uso por un usuario externo del tenant.');
                    }
                },
            ],
            'active' => 'sometimes|boolean',
            'role' => [
                'sometimes',
                'string',
                Rule::in(Role::values()),
                function (string $_attribute, mixed $value, \Closure $fail) {
                    $param = $this->route('user');
                    $targetId = $param instanceof User ? $param->id : (int) $param;
                    if ($this->user()->id === $targetId) {
                        $fail('No puedes cambiar tu propio rol.');
                        return;
                    }
                    $elevatedRoles = [Role::Administrador->value, Role::Tecnico->value];
                    if (
                        ! $this->user()->hasAnyRole($elevatedRoles)
                        && in_array($value, $elevatedRoles, true)
                    ) {
                        $fail('No tienes permisos para asignar el rol de administrador o técnico.');
                    }
                },
            ],
        ];
    }
}
