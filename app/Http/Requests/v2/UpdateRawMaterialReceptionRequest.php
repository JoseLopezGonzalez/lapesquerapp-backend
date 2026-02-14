<?php

namespace App\Http\Requests\v2;

use App\Models\RawMaterialReception;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRawMaterialReceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $id = $this->route('raw_material_reception');
        if ($id instanceof RawMaterialReception) {
            return $this->user()->can('update', $id);
        }
        if (!$id) {
            return false;
        }
        return $this->user()->can('update', RawMaterialReception::findOrFail($id));
    }

    public function rules(): array
    {
        $reception = $this->getReception();
        if (!$reception) {
            return [
                'supplier.id' => 'required',
                'date' => 'required|date',
            ];
        }

        $base = [
            'supplier.id' => 'required|exists:tenant.suppliers,id',
            'date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
            'declaredTotalAmount' => 'nullable|numeric|min:0',
            'declaredTotalNetWeight' => 'nullable|numeric|min:0',
        ];

        if ($reception->creation_mode === RawMaterialReception::CREATION_MODE_LINES || $reception->creation_mode === null) {
            $base['details'] = 'required|array|min:1';
            $base['details.*.product.id'] = 'required|exists:tenant.products,id';
            $base['details.*.netWeight'] = 'required|numeric|min:0';
            $base['details.*.price'] = 'nullable|numeric|min:0';
            $base['details.*.lot'] = 'nullable|string|max:255';
            $base['details.*.boxes'] = 'nullable|integer|min:0';
            return $base;
        }

        if ($reception->creation_mode === RawMaterialReception::CREATION_MODE_PALLETS) {
            $base['pallets'] = 'required|array|min:1';
            $base['pallets.*.id'] = 'nullable|integer|exists:tenant.pallets,id';
            $base['pallets.*.observations'] = 'nullable|string|max:1000';
            $base['pallets.*.store.id'] = 'nullable|integer|exists:tenant.stores,id';
            $base['pallets.*.boxes'] = 'required|array|min:1';
            $base['pallets.*.boxes.*.id'] = 'nullable|integer|exists:tenant.boxes,id';
            $base['pallets.*.boxes.*.product.id'] = 'required|exists:tenant.products,id';
            $base['pallets.*.boxes.*.lot'] = 'nullable|string|max:255';
            $base['pallets.*.boxes.*.gs1128'] = 'required|string|max:255';
            $base['pallets.*.boxes.*.grossWeight'] = 'required|numeric|min:0';
            $base['pallets.*.boxes.*.netWeight'] = 'required|numeric|min:0.01';
            $base['prices'] = 'required|array|min:1';
            $base['prices.*.product.id'] = 'required|exists:tenant.products,id';
            $base['prices.*.lot'] = 'required|string|max:255';
            $base['prices.*.price'] = 'required|numeric|min:0';
            return $base;
        }

        $base['details'] = 'required|array|min:1';
        $base['details.*.product.id'] = 'required|exists:tenant.products,id';
        $base['details.*.netWeight'] = 'required|numeric|min:0';
        $base['details.*.price'] = 'nullable|numeric|min:0';
        $base['details.*.lot'] = 'nullable|string|max:255';
        $base['details.*.boxes'] = 'nullable|integer|min:0';
        return $base;
    }

    private function getReception(): ?RawMaterialReception
    {
        $id = $this->route('raw_material_reception');
        if ($id instanceof RawMaterialReception) {
            return $id;
        }
        if (!$id) {
            return null;
        }
        return RawMaterialReception::find($id);
    }
}
