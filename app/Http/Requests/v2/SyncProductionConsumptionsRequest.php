<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class SyncProductionConsumptionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Accept a root-level JSON array (including []) as the consumptions list, so clients can send
     * [] or [{...}] in addition to {"consumptions": []} / {"consumptions": [...]}.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->isJson()) {
            return;
        }

        $all = $this->all();

        if (array_is_list($all) && ! array_key_exists('consumptions', $all)) {
            $this->merge(['consumptions' => $all]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Keep sync semantics consistent: allow explicit empty array to remove all consumptions.
            'consumptions' => 'present|array',
            'consumptions.*.id' => 'sometimes|nullable|integer|exists:tenant.production_output_consumptions,id',
            'consumptions.*.production_output_id' => 'required|exists:tenant.production_outputs,id',
            'consumptions.*.consumed_weight_kg' => 'required|numeric|gt:0',
            'consumptions.*.consumed_boxes' => 'nullable|integer|min:0',
            'consumptions.*.notes' => 'nullable|string',
        ];
    }
}
