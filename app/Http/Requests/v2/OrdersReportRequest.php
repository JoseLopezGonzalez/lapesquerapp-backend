<?php

namespace App\Http\Requests\v2;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class OrdersReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Order::class);
    }

    /**
     * Parámetros opcionales para filtrar el reporte de pedidos (OrderExport).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customers' => 'nullable|array',
            'customers.*' => 'integer',
            'id' => 'nullable|string',
            'ids' => 'nullable|array',
            'ids.*' => 'integer',
            'buyerReference' => 'nullable|string',
            'status' => 'nullable|string|in:pending,finished,incident',
            'loadDate' => 'nullable|array',
            'loadDate.start' => 'nullable|date',
            'loadDate.end' => 'nullable|date',
            'entryDate' => 'nullable|array',
            'entryDate.start' => 'nullable|date',
            'entryDate.end' => 'nullable|date',
            'transports' => 'nullable|array',
            'transports.*' => 'integer',
            'salespeople' => 'nullable|array',
            'salespeople.*' => 'integer',
            'palletsState' => 'nullable|string|in:stored,shipping',
        ];
    }
}
