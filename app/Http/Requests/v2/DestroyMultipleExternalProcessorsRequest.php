<?php

namespace App\Http\Requests\v2;

use App\Models\ExternalProcessor;
use Illuminate\Foundation\Http\FormRequest;

class DestroyMultipleExternalProcessorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', ExternalProcessor::class);
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:tenant.external_processors,id'],
        ];
    }
}
