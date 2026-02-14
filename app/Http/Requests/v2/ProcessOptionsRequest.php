<?php

namespace App\Http\Requests\v2;

use App\Models\Process;
use Illuminate\Foundation\Http\FormRequest;

class ProcessOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Process::class);
    }

    public function rules(): array
    {
        return [
            'type' => 'nullable|string|in:starting,process,final',
        ];
    }
}
