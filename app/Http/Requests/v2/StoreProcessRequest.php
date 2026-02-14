<?php

namespace App\Http\Requests\v2;

use App\Models\Process;
use Illuminate\Foundation\Http\FormRequest;

class StoreProcessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Process::class);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|min:2',
            'type' => 'required|in:starting,process,final',
        ];
    }
}
