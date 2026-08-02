<?php

namespace App\Http\Requests\v2;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @queryParam active string Deprecated: cuando se envía, la respuesta cambia de forma (`{ data: [...] }`
 *   sin `links`/`meta`, en vez del sobre de paginación estándar completo). Usa `GET /v2/orders/active`
 *   para el listado de pedidos activos (forma estable, ActiveOrderCardResource). Example: true
 * @queryParam perPage integer Elementos por página (máx. 100). Example: 15
 * @queryParam status string Filtra por estado. Example: pending
 * @queryParam orderType string Filtra por tipo de pedido. Example: standard
 */
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
            'invoiced' => 'sometimes|string|in:true,false',
            'orderType' => 'sometimes|string|in:standard,autoventa',
            'loadDate' => 'sometimes|array',
            'loadDate.start' => 'sometimes|date',
            'loadDate.end' => 'sometimes|date',
            'entryDate' => 'sometimes|array',
            'entryDate.start' => 'sometimes|date',
            'entryDate.end' => 'sometimes|date',
            'transports' => 'sometimes|array',
            'transports.*' => 'integer',
            'externalProcessors' => 'sometimes|array',
            'externalProcessors.*' => 'integer',
            'salespeople' => 'sometimes|array',
            'salespeople.*' => 'integer',
            'fieldOperators' => 'sometimes|array',
            'fieldOperators.*' => 'integer',
            'palletsState' => 'sometimes|string|in:stored,shipping',
            'products' => 'sometimes|array',
            'products.*' => 'integer',
            'species' => 'sometimes|array',
            'species.*' => 'integer',
            'incoterm' => 'sometimes|integer',
            'transport' => 'sometimes|integer',
            'routeId' => 'sometimes|integer',
            'perPage' => 'sometimes|integer|min:1|max:100',
        ];
    }
}
