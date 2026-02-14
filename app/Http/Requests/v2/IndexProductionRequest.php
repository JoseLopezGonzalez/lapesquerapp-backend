<?php

namespace App\Http\Requests\v2;

use App\Models\Production;
use Illuminate\Foundation\Http\FormRequest;

class IndexProductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Production::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'lot' => 'nullable|string|max:255',
            'species_id' => 'nullable|exists:tenant.species,id',
            'status' => 'nullable|string|in:open,closed',
            'perPage' => 'nullable|integer|min:1|max:100',
        ];
    }
}
