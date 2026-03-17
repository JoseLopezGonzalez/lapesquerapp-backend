<?php

namespace App\Http\Requests\v2;

use App\Models\Offer;
use Illuminate\Foundation\Http\FormRequest;

class IndexOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Offer::class);
    }

    public function rules(): array
    {
        return [
            'search' => 'sometimes|string|max:255',
            'status' => 'sometimes|array',
            'status.*' => 'string|in:draft,sent,accepted,rejected,expired',
            'prospectId' => 'sometimes|integer|exists:tenant.prospects,id',
            'customerId' => 'sometimes|integer|exists:tenant.customers,id',
            'orderId' => 'sometimes|integer|exists:tenant.orders,id',
            'salespeople' => 'sometimes|array',
            'salespeople.*' => 'integer',
            'perPage' => 'sometimes|integer|min:1|max:100',
        ];
    }
}
