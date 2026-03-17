<?php

namespace App\Http\Requests\v2;

use App\Models\Prospect;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProspectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Prospect::class);
    }

    public function rules(): array
    {
        return [
            'companyName' => 'required|string|max:255',
            'countryId' => 'nullable|integer|exists:tenant.countries,id',
            'speciesInterest' => 'nullable|array',
            'speciesInterest.*' => 'string|max:255',
            'origin' => ['nullable', 'string', Rule::in(Prospect::origins())],
            'status' => ['nullable', 'string', Rule::in(Prospect::statuses())],
            'notes' => 'nullable|string',
            'commercialInterestNotes' => 'nullable|string',
            'nextActionAt' => 'nullable|date',
            'lostReason' => 'nullable|string',
            'salespersonId' => 'nullable|integer|exists:tenant.salespeople,id',
            'primaryContact.name' => 'nullable|string|max:255',
            'primaryContact.role' => 'nullable|string|max:255',
            'primaryContact.phone' => 'nullable|string|max:255',
            'primaryContact.email' => 'nullable|email:rfc|max:255',
        ];
    }
}
