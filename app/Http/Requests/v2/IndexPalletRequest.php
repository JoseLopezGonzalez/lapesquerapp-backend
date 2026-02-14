<?php

namespace App\Http\Requests\v2;

use App\Models\Pallet;
use Illuminate\Foundation\Http\FormRequest;

class IndexPalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Pallet::class);
    }

    public function rules(): array
    {
        return [
            'id' => 'nullable',
            'ids' => 'nullable|array',
            'ids.*' => 'integer',
            'state' => 'nullable|string|in:registered,stored,shipped,processed',
            'orderState' => 'nullable|string|in:pending,finished,without_order',
            'store' => 'nullable|array',
            'store.id' => 'nullable|integer',
            'position' => 'nullable|string|in:located,unlocated',
            'products' => 'nullable|array',
            'products.*' => 'integer',
            'species' => 'nullable|array',
            'species.*' => 'integer',
            'lots' => 'nullable|array',
            'lots.*' => 'string|max:255',
            'dateFrom' => 'nullable|date',
            'dateTo' => 'nullable|date',
            'orderIds' => 'nullable|array',
            'orderIds.*' => 'integer',
            'orderDates' => 'nullable|array',
            'orderDates.start' => 'nullable|date',
            'orderDates.end' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
            'buyerReference' => 'nullable|string|max:255',
            'perPage' => 'nullable|integer|min:1|max:100',
            'filters' => 'nullable|array',
        ];
    }
}
