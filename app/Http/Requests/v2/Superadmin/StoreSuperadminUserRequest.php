<?php

namespace App\Http\Requests\v2\Superadmin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSuperadminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:mysql.superadmin_users,email'],
            'send_access_email' => ['boolean'],
        ];
    }
}
