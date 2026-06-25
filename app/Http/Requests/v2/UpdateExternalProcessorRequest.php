<?php

namespace App\Http\Requests\v2;

use App\Models\ExternalProcessor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExternalProcessorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $externalProcessor = $this->route('externalProcessor');
        $model = $externalProcessor instanceof ExternalProcessor
            ? $externalProcessor
            : ExternalProcessor::find($externalProcessor);

        return $model ? $this->user()->can('update', $model) : true;
    }

    public function rules(): array
    {
        $externalProcessor = $this->route('externalProcessor');
        $id = $externalProcessor instanceof ExternalProcessor
            ? $externalProcessor->id
            : $externalProcessor;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'legalName' => ['nullable', 'string', 'max:255'],
            'vatNumber' => ['sometimes', 'required', 'string', 'max:32', Rule::unique(ExternalProcessor::class, 'vat_number')->ignore($id)],
            'sanitaryRegistrationNumber' => ['nullable', 'string', 'max:64'],
            'contactPerson' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'emails' => ['nullable', 'array'],
            'emails.*' => ['string', 'email:rfc,dns', 'distinct'],
            'ccEmails' => ['nullable', 'array'],
            'ccEmails.*' => ['string', 'email:rfc,dns', 'distinct'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:255'],
            'postalCode' => ['nullable', 'string', 'max:20'],
            'province' => ['nullable', 'string', 'max:255'],
            'countryId' => ['nullable', 'integer', 'exists:tenant.countries,id'],
            'isActive' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
