<?php

namespace App\Http\Requests\v2;

use App\Models\Process;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProcessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|min:2',
            'type' => 'sometimes|required|in:starting,process,final',
        ];
    }
}
