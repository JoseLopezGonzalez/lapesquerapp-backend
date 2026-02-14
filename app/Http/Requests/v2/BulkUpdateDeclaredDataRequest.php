<?php

namespace App\Http\Requests\v2;

use App\Models\RawMaterialReception;
use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateDeclaredDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', RawMaterialReception::class);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('receptions') || ! is_array($this->receptions)) {
            return;
        }
        $this->merge([
            'receptions' => array_map(function (array $r): array {
                return [
                    'supplier_id' => $r['supplier_id'] ?? $r['supplierId'] ?? null,
                    'date' => $r['date'] ?? null,
                    'declared_total_amount' => $r['declared_total_amount'] ?? $r['declaredTotalAmount'] ?? null,
                    'declared_total_net_weight' => $r['declared_total_net_weight'] ?? $r['declaredTotalNetWeight'] ?? null,
                ];
            }, $this->receptions),
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'receptions' => 'required|array|min:1',
            'receptions.*.supplier_id' => 'required|integer|exists:tenant.suppliers,id',
            'receptions.*.date' => 'required|date',
            'receptions.*.declared_total_amount' => 'nullable|numeric|min:0',
            'receptions.*.declared_total_net_weight' => 'nullable|numeric|min:0',
        ];
    }
}
