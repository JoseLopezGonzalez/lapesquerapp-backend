<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class IndexFieldOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('createAutoventaOperational', \App\Models\Order::class);
    }

    public function rules(): array
    {
        return [
            'status' => 'nullable|string|in:pending,finished,incident',
            'orderType' => 'nullable|string|in:standard,autoventa',
            'routeId' => 'nullable|integer|exists:tenant.routes,id',
            'perPage' => 'nullable|integer|min:1|max:100',
        ];
    }
}
