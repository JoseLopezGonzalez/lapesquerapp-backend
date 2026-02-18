<?php

namespace App\Http\Requests\v2;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');
        if ($product instanceof Product) {
            return $this->user()->can('update', $product);
        }
        if (!$product) {
            return false;
        }
        return $this->user()->can('update', Product::findOrFail($product));
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();
        if (isset($data['species_id']) && !isset($data['speciesId'])) {
            $data['speciesId'] = $data['species_id'];
        }
        if (isset($data['capture_zone_id']) && !isset($data['captureZoneId'])) {
            $data['captureZoneId'] = $data['capture_zone_id'];
        }
        if (isset($data['family_id']) && !isset($data['familyId'])) {
            $data['familyId'] = $data['family_id'];
        }
        if (isset($data['article_gtin']) && !isset($data['articleGtin'])) {
            $data['articleGtin'] = $data['article_gtin'];
        }
        if (isset($data['box_gtin']) && !isset($data['boxGtin'])) {
            $data['boxGtin'] = $data['box_gtin'];
        }
        if (isset($data['pallet_gtin']) && !isset($data['palletGtin'])) {
            $data['palletGtin'] = $data['pallet_gtin'];
        }
        foreach (['articleGtin', 'boxGtin', 'palletGtin'] as $key) {
            if (isset($data[$key]) && $data[$key] === '') {
                $data[$key] = null;
            }
        }
        $this->merge($data);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->route('product');
        $id = $id instanceof Product ? $id->id : $id;

        return [
            'name' => 'sometimes|required|string|min:3|max:255|unique:tenant.products,name,' . $id,
            'speciesId' => 'sometimes|required|exists:tenant.species,id',
            'captureZoneId' => 'sometimes|required|exists:tenant.capture_zones,id',
            'familyId' => 'nullable|exists:tenant.product_families,id',
            'articleGtin' => 'nullable|string|regex:/^[0-9]{13}$/|size:13|unique:tenant.products,article_gtin,' . $id,
            'boxGtin' => 'nullable|string|regex:/^[0-9]{14}$/|size:14|unique:tenant.products,box_gtin,' . $id,
            'palletGtin' => 'nullable|string|regex:/^[0-9]{8,14}$/|max:14|unique:tenant.products,pallet_gtin,' . $id,
            'a3erp_code' => 'nullable|string|max:255',
            'facil_com_code' => 'nullable|string|max:255',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Ya existe un producto con este nombre.',
            'articleGtin.regex' => 'El GTIN del artículo debe tener exactamente 13 dígitos.',
            'articleGtin.unique' => 'Ya existe un producto con este GTIN de artículo.',
            'boxGtin.regex' => 'El GTIN de la caja debe tener exactamente 14 dígitos.',
            'boxGtin.unique' => 'Ya existe un producto con este GTIN de caja.',
            'palletGtin.regex' => 'El GTIN del palet debe tener entre 8 y 14 dígitos numéricos.',
            'palletGtin.unique' => 'Ya existe un producto con este GTIN de palet.',
        ];
    }

    /**
     * Datos validados en formato para Product::update() (snake_case).
     *
     * @return array<string, mixed>
     */
    public function getUpdateData(): array
    {
        $v = $this->validated();
        $map = [
            'name' => 'name',
            'speciesId' => 'species_id',
            'captureZoneId' => 'capture_zone_id',
            'familyId' => 'family_id',
            'articleGtin' => 'article_gtin',
            'boxGtin' => 'box_gtin',
            'palletGtin' => 'pallet_gtin',
            'a3erp_code' => 'a3erp_code',
            'facil_com_code' => 'facil_com_code',
        ];
        $out = [];
        foreach ($map as $key => $dbKey) {
            if (array_key_exists($key, $v)) {
                $out[$dbKey] = $v[$key];
            }
        }
        return $out;
    }
}
