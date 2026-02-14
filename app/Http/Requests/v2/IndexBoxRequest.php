<?php

namespace App\Http\Requests\v2;

use App\Models\Box;
use Illuminate\Foundation\Http\FormRequest;

class IndexBoxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Box::class);
    }

    /**
     * Filtros opcionales para listado de cajas.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'available' => 'nullable|string|in:true,false',
            'id' => 'nullable|integer',
            'ids' => 'nullable|array',
            'ids.*' => 'integer',
            'name' => 'nullable|string|max:255',
            'species' => 'nullable|array',
            'species.*' => 'integer',
            'lots' => 'nullable|array',
            'lots.*' => 'string|max:255',
            'products' => 'nullable|array',
            'products.*' => 'integer',
            'pallets' => 'nullable|array',
            'pallets.*' => 'integer',
            'gs1128' => 'nullable|array',
            'gs1128.*' => 'string|max:255',
            'createdAt' => 'nullable|array',
            'createdAt.start' => 'nullable|date',
            'createdAt.end' => 'nullable|date',
            'palletState' => 'nullable|string|in:stored,shipped',
            'orderState' => 'nullable',
            'position' => 'nullable|string|in:located,unlocated',
            'stores' => 'nullable|array',
            'stores.*' => 'integer',
            'orders' => 'nullable|array',
            'orders.*' => 'integer',
            'notes' => 'nullable|string|max:500',
            'orderIds' => 'nullable|array',
            'orderIds.*' => 'integer',
            'orderDates' => 'nullable|array',
            'orderDates.start' => 'nullable|date',
            'orderDates.end' => 'nullable|date',
            'orderBuyerReference' => 'nullable|string|max:255',
            'perPage' => 'nullable|integer|min:1|max:100',
        ];
    }
}
