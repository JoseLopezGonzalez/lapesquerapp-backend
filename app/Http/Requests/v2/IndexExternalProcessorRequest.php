<?php

namespace App\Http\Requests\v2;

use App\Models\ExternalProcessor;
use Illuminate\Foundation\Http\FormRequest;

class IndexExternalProcessorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', ExternalProcessor::class);
    }

    public function rules(): array
    {
        return [
            'id' => ['nullable', 'integer'],
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer'],
            'name' => ['nullable', 'string', 'max:255'],
            'vatNumber' => ['nullable', 'string', 'max:32'],
            'sanitaryRegistrationNumber' => ['nullable', 'string', 'max:64'],
            'isActive' => ['nullable', 'boolean'],
            'countryId' => ['nullable', 'integer', 'exists:tenant.countries,id'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
