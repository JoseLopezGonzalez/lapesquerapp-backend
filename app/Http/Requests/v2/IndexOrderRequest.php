<?php

namespace App\Http\Requests\v2;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class IndexOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Order::class);
    }

    public function rules(): array
    {
        return [
            'active' => 'sometimes|string|in:true,false',
            'id' => 'sometimes|string',
            'ids' => 'sometimes|array',
            'ids.*' => 'integer',
            'customers' => 'sometimes|array',
            'customers.*' => 'integer',
            'buyerReference' => 'sometimes|string',
            'status' => 'sometimes|string|in:pending,finished,incident',
            'orderType' => 'sometimes|string|in:standard,autoventa',
            'loadDate' => 'sometimes|array',
            'loadDate.start' => 'sometimes|date',
            'loadDate.end' => 'sometimes|date',
            'entryDate' => 'sometimes|array',
            'entryDate.start' => 'sometimes|date',
            'entryDate.end' => 'sometimes|date',
            'transports' => 'sometimes|array',
            'transports.*' => 'integer',
            'salespeople' => 'sometimes|array',
            'salespeople.*' => 'integer',
            'palletsState' => 'sometimes|string|in:stored,shipping',
            'products' => 'sometimes|array',
            'products.*' => 'integer',
            'species' => 'sometimes|array',
            'species.*' => 'integer',
            'incoterm' => 'sometimes|integer',
            'transport' => 'sometimes|integer',
            'perPage' => 'sometimes|integer|min:1|max:100',
        ];
    }
}
