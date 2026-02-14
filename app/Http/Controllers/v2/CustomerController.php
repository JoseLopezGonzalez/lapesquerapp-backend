<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\DestroyMultipleCustomersRequest;
use App\Http\Requests\v2\IndexCustomerRequest;
use App\Http\Requests\v2\StoreCustomerRequest;
use App\Http\Requests\v2\UpdateCustomerRequest;
use App\Http\Resources\v2\CustomerResource;
use App\Models\Customer;
use App\Models\Order;
use App\Services\v2\CustomerListService;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(IndexCustomerRequest $request)
    {
        $this->authorize('viewAny', Customer::class);

        return CustomerResource::collection(CustomerListService::list($request));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCustomerRequest $request)
    {
        $this->authorize('create', Customer::class);

        $validated = $request->validated();

        $allEmails = [];
        foreach ($validated['emails'] ?? [] as $email) {
            $allEmails[] = trim($email) . ';';
        }
        foreach ($validated['ccEmails'] ?? [] as $ccEmail) {
            $allEmails[] = 'CC:' . trim($ccEmail) . ';';
        }
        $validated['emails'] = !empty($allEmails) ? implode("\n", $allEmails) : null;
        unset($validated['ccEmails']);

        $data = [
            'name' => $validated['name'],
            'vat_number' => $validated['vatNumber'] ?? null,
            'billing_address' => $validated['billing_address'] ?? null,
            'shipping_address' => $validated['shipping_address'] ?? null,
            'transportation_notes' => $validated['transportation_notes'] ?? null,
            'production_notes' => $validated['production_notes'] ?? null,
            'accounting_notes' => $validated['accounting_notes'] ?? null,
            'emails' => $validated['emails'] ?? null,
            'contact_info' => $validated['contact_info'] ?? null,
            'salesperson_id' => $validated['salesperson_id'] ?? null,
            'country_id' => $validated['country_id'] ?? null,
            'payment_term_id' => $validated['payment_term_id'] ?? null,
            'transport_id' => $validated['transport_id'] ?? null,
            'a3erp_code' => $validated['a3erp_code'] ?? null,
            'facilcom_code' => $validated['facilcom_code'] ?? null,
        ];

        $customer = Customer::create($data);
        $customer->alias = "Cliente Nº " . $customer->id;
        $customer->save();

        return response()->json([
            'message' => 'Cliente creado correctamente.',
            'data' => new CustomerResource($customer),
        ], 201);
    }




    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $customer = Customer::findOrFail($id);
        $this->authorize('view', $customer);

        return response()->json([
            'data' => new CustomerResource($customer),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCustomerRequest $request, string $id)
    {
        $customer = Customer::findOrFail($id);
        $this->authorize('update', $customer);

        $validated = $request->validated();

        $allEmails = [];
        foreach ($validated['emails'] ?? [] as $email) {
            $allEmails[] = trim($email) . ';';
        }
        foreach ($validated['ccEmails'] ?? [] as $ccEmail) {
            $allEmails[] = 'CC:' . trim($ccEmail) . ';';
        }
        $validated['emails'] = !empty($allEmails) ? implode("\n", $allEmails) : null;
        unset($validated['ccEmails']);

        $data = [
            'name' => $validated['name'],
            'vat_number' => $validated['vatNumber'] ?? null,
            'billing_address' => $validated['billing_address'] ?? null,
            'shipping_address' => $validated['shipping_address'] ?? null,
            'transportation_notes' => $validated['transportation_notes'] ?? null,
            'production_notes' => $validated['production_notes'] ?? null,
            'accounting_notes' => $validated['accounting_notes'] ?? null,
            'emails' => $validated['emails'] ?? null,
            'contact_info' => $validated['contact_info'] ?? null,
            'salesperson_id' => $validated['salesperson_id'] ?? null,
            'country_id' => $validated['country_id'] ?? null,
            'payment_term_id' => $validated['payment_term_id'] ?? null,
            'transport_id' => $validated['transport_id'] ?? null,
            'a3erp_code' => $validated['a3erp_code'] ?? null,
            'facilcom_code' => $validated['facilcom_code'] ?? null,
        ];

        $customer->update($data);
        $customer->alias = "Cliente Nº " . $customer->id;
        $customer->save();

        return response()->json([
            'message' => 'Cliente actualizado correctamente.',
            'data' => new CustomerResource($customer),
        ]);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $customer = Customer::findOrFail($id);
        $this->authorize('delete', $customer);

        $usedInOrders = $customer->orders()->exists();

        if ($usedInOrders) {
            return response()->json([
                'message' => 'No se puede eliminar el cliente porque está en uso',
                'details' => 'El cliente está siendo utilizado en pedidos',
                'userMessage' => 'No se puede eliminar el cliente porque está siendo utilizado en pedidos'
            ], 400);
        }

        $customer->delete();

        return response()->json(['message' => 'Cliente eliminado con éxito']);
    }

    public function destroyMultiple(DestroyMultipleCustomersRequest $request)
    {
        $this->authorize('viewAny', Customer::class);

        $validated = $request->validated();
        $customers = Customer::whereIn('id', $validated['ids'])->get();
        
        // Validar si alguno de los clientes está en uso
        $inUse = [];
        foreach ($customers as $customer) {
            $usedInOrders = $customer->orders()->exists();
            
            if ($usedInOrders) {
                $inUse[] = [
                    'id' => $customer->id,
                    'name' => $customer->name,
                ];
            }
        }

        if (!empty($inUse)) {
            $message = 'No se pueden eliminar algunos clientes porque están en uso: ';
            $details = array_map(function($item) {
                return $item['name'] . ' (usado en pedidos)';
            }, $inUse);
            
            return response()->json([
                'message' => 'No se pueden eliminar algunos clientes porque están en uso',
                'details' => implode(', ', $details),
                'userMessage' => $message . implode(', ', array_column($inUse, 'name'))
            ], 400);
        }

        Customer::whereIn('id', $validated['ids'])->delete();

        return response()->json(['message' => 'Clientes eliminados con éxito']);
    }

    /**
     * Get all options for the customers select box.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function options()
    {
        $this->authorize('viewAny', Customer::class);

        $customers = Customer::select('id', 'name')
            ->orderBy('name', 'asc') // Ordena por nombre, opcional
            ->get();

        return response()->json($customers);
    }

    /**
     * Obtener el historial completo de pedidos del cliente.
     * Devuelve un resumen de todos los productos pedidos por el cliente, incluyendo todos los pedidos.
     * Soporta filtrado por período mediante parámetros de query.
     */
    public function getOrderHistory(Request $request, string $id)
    {
        $customer = Customer::findOrFail($id);
        $this->authorize('view', $customer);

        // Calcular años disponibles desde todos los pedidos históricos (sin filtros)
        $availableYears = Order::where('customer_id', $customer->id)
            ->whereNotNull('load_date')
            ->selectRaw('YEAR(load_date) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->map(function ($year) {
                return (int) $year; // Asegurar que sean enteros
            })
            ->filter()
            ->values()
            ->toArray();

        // Construir query para pedidos filtrados
        $ordersQuery = Order::where('customer_id', $customer->id);

        // Aplicar filtros según parámetros de query
        $this->applyOrderHistoryFilters($ordersQuery, $request);

        // Obtener pedidos filtrados con relaciones necesarias
        $orders = $ordersQuery
            ->with([
                'plannedProductDetails.product',
                'pallets.boxes.box.product',
                'pallets.boxes.box.productionInputs'
            ])
            ->orderBy('load_date', 'desc')
            ->get();

        $history = [];

        foreach ($orders as $order) {
            // Usar el atributo dinámico productDetails del modelo Order
            foreach ($order->productDetails as $detail) {
                $productId = $detail['product']['id'];

                if (!isset($history[$productId])) {
                    // Formatear fecha inicial como YYYY-MM-DD
                    $initialLoadDate = null;
                    if ($order->load_date) {
                        if ($order->load_date instanceof \Carbon\Carbon || $order->load_date instanceof \DateTime) {
                            $initialLoadDate = $order->load_date->format('Y-m-d');
                        } else {
                            $initialLoadDate = date('Y-m-d', strtotime($order->load_date));
                        }
                    }

                    // Estructura del producto según especificación
                    $history[$productId] = [
                        'product' => [
                            'id' => $detail['product']['id'],
                            'name' => $detail['product']['name'],
                            'a3erpCode' => $detail['product']['a3erpCode'] ?? null,
                            'facilcomCode' => $detail['product']['facilcomCode'] ?? null,
                            'species_id' => $detail['product']['species_id'] ?? null,
                        ],
                        'total_boxes' => 0,
                        'total_net_weight' => 0,
                        'average_unit_price' => 0,
                        'last_order_date' => $initialLoadDate,
                        'lines' => [],
                        'total_amount' => 0,
                    ];
                }

                // Actualizar valores acumulados
                $history[$productId]['total_boxes'] += $detail['boxes'];
                $history[$productId]['total_net_weight'] += $detail['netWeight'];
                // Usar 'total' (con impuestos) para el cálculo del promedio, no 'subtotal'
                $history[$productId]['total_amount'] += $detail['total'];

                // Formatear fecha como YYYY-MM-DD
                $loadDate = null;
                if ($order->load_date) {
                    if ($order->load_date instanceof \Carbon\Carbon || $order->load_date instanceof \DateTime) {
                        $loadDate = $order->load_date->format('Y-m-d');
                    } else {
                        $loadDate = date('Y-m-d', strtotime($order->load_date));
                    }
                }

                // Registrar línea individual
                $history[$productId]['lines'][] = [
                    'order_id' => $order->id,
                    'formatted_id' => $order->formatted_id,
                    'load_date' => $loadDate,
                    'boxes' => (int) $detail['boxes'],
                    'net_weight' => round((float) $detail['netWeight'], 2),
                    'unit_price' => $detail['unitPrice'], // Puede ser string o number según especificación
                    'subtotal' => round((float) $detail['subtotal'], 2),
                    'total' => round((float) $detail['total'], 2),
                ];

                // Actualizar la última fecha de pedido si es más reciente (comparar strings YYYY-MM-DD)
                if ($loadDate && (!$history[$productId]['last_order_date'] || strcmp($loadDate, $history[$productId]['last_order_date']) > 0)) {
                    $history[$productId]['last_order_date'] = $loadDate;
                }
            }
        }

        // Calcular el precio medio ponderado, ordenar líneas y calcular trend
        $previousPeriodDates = $this->getPreviousPeriodDates($request);
        
        // Obtener pesos netos del período anterior para todos los productos (una sola consulta)
        $previousPeriodNetWeights = [];
        if ($previousPeriodDates) {
            $previousOrders = Order::where('customer_id', $customer->id)
                ->whereBetween('load_date', [$previousPeriodDates['from'], $previousPeriodDates['to']])
                ->with([
                    'plannedProductDetails.product',
                    'pallets.boxes.box.product',
                    'pallets.boxes.box.productionInputs'
                ])
                ->get();

            // Agrupar por producto y sumar pesos netos
            foreach ($previousOrders as $order) {
                foreach ($order->productDetails as $detail) {
                    $productId = $detail['product']['id'];
                    if (!isset($previousPeriodNetWeights[$productId])) {
                        $previousPeriodNetWeights[$productId] = 0;
                    }
                    $previousPeriodNetWeights[$productId] += $detail['netWeight'];
                }
            }
        }
        
        foreach ($history as &$product) {
            // Calcular average_unit_price: total_amount / total_net_weight
            if ($product['total_net_weight'] > 0) {
                $product['average_unit_price'] = round($product['total_amount'] / $product['total_net_weight'], 2);
            } else {
                $product['average_unit_price'] = 0;
            }

            // Ordenar líneas por load_date descendente (más reciente primero)
            usort($product['lines'], function ($a, $b) {
                return strcmp($b['load_date'], $a['load_date']);
            });

            // Asegurar tipos numéricos correctos
            $product['total_boxes'] = (int) $product['total_boxes'];
            $product['total_net_weight'] = round((float) $product['total_net_weight'], 2);
            $product['total_amount'] = round((float) $product['total_amount'], 2);

            // Calcular trend si hay período anterior definido
            if ($previousPeriodDates) {
                $productId = $product['product']['id'];
                $previousNetWeight = $previousPeriodNetWeights[$productId] ?? 0;
                
                $trend = $this->calculateTrendValue(
                    $product['total_net_weight'],
                    $previousNetWeight
                );
                
                // Siempre agregar trend si hay período anterior (puede ser stable con 0%)
                $product['trend'] = $trend;
            }
        }

        // Reindexar para devolver un array limpio
        $history = array_values($history);

        return response()->json([
            'message' => 'Historial de pedidos del cliente obtenido correctamente.',
            'available_years' => $availableYears,
            'data' => $history,
        ]);
    }

    /**
     * Aplicar filtros a la query de pedidos según parámetros de request.
     * 
     * @param mixed $query Query builder de Eloquent
     * @param \Illuminate\Http\Request $request
     * @return void
     */
    private function applyOrderHistoryFilters($query, Request $request)
    {
        // Opción 1: Filtro por rango de fechas (date_from y date_to)
        if ($request->has('date_from') && $request->has('date_to')) {
            $dateFrom = date('Y-m-d 00:00:00', strtotime($request->date_from));
            $dateTo = date('Y-m-d 23:59:59', strtotime($request->date_to));
            $query->whereBetween('load_date', [$dateFrom, $dateTo]);
            return; // Si hay rango de fechas, no aplicar otros filtros
        }

        // Opción 2: Filtro por año específico
        if ($request->has('year')) {
            $year = (int) $request->year;
            $query->whereYear('load_date', $year);
            return; // Si hay año, no aplicar otros filtros
        }

        // Opción 3: Filtro por tipo de período (month, quarter, year)
        if ($request->has('period')) {
            $period = $request->period;
            $now = \Carbon\Carbon::now();

            switch ($period) {
                case 'month':
                    // Mes actual
                    $query->whereYear('load_date', $now->year)
                          ->whereMonth('load_date', $now->month);
                    break;

                case 'quarter':
                    // Trimestre actual
                    $startOfQuarter = $now->copy()->startOfQuarter();
                    $endOfQuarter = $now->copy()->endOfQuarter();
                    $query->whereBetween('load_date', [
                        $startOfQuarter->format('Y-m-d 00:00:00'),
                        $endOfQuarter->format('Y-m-d 23:59:59')
                    ]);
                    break;

                case 'year':
                    // Año actual
                    $query->whereYear('load_date', $now->year);
                    break;

                default:
                    // Si el período no es válido, no aplicar filtro (devolver todo)
                    break;
            }
        }

        // Si no hay ningún filtro, la query devolverá todos los pedidos
    }

    /**
     * Obtener las fechas del período anterior según el filtro aplicado.
     * 
     * @param \Illuminate\Http\Request $request
     * @return array|null Array con 'from' y 'to' o null si no hay filtro
     */
    private function getPreviousPeriodDates(Request $request): ?array
    {
        // Opción 1: Filtro por rango de fechas (date_from y date_to)
        if ($request->has('date_from') && $request->has('date_to')) {
            $dateFrom = \Carbon\Carbon::parse($request->date_from);
            $dateTo = \Carbon\Carbon::parse($request->date_to);
            
            // Calcular la duración del período
            $daysDiff = $dateFrom->diffInDays($dateTo);
            
            // Calcular período anterior del mismo rango
            $previousDateEnd = $dateFrom->copy()->subDay()->endOfDay();
            $previousDateStart = $previousDateEnd->copy()->subDays($daysDiff)->startOfDay();
            
            return [
                'from' => $previousDateStart->format('Y-m-d 00:00:00'),
                'to' => $previousDateEnd->format('Y-m-d 23:59:59'),
            ];
        }

        // Opción 2: Filtro por año específico
        if ($request->has('year')) {
            $year = (int) $request->year;
            $previousYear = $year - 1;
            
            return [
                'from' => $previousYear . '-01-01 00:00:00',
                'to' => $previousYear . '-12-31 23:59:59',
            ];
        }

        // Opción 3: Filtro por tipo de período (month, quarter, year)
        if ($request->has('period')) {
            $period = $request->period;
            $now = \Carbon\Carbon::now();

            switch ($period) {
                case 'month':
                    // Mes anterior
                    $previousMonth = $now->copy()->subMonth();
                    return [
                        'from' => $previousMonth->copy()->startOfMonth()->format('Y-m-d 00:00:00'),
                        'to' => $previousMonth->copy()->endOfMonth()->format('Y-m-d 23:59:59'),
                    ];

                case 'quarter':
                    // Trimestre anterior
                    $previousQuarter = $now->copy()->subQuarter();
                    return [
                        'from' => $previousQuarter->copy()->startOfQuarter()->format('Y-m-d 00:00:00'),
                        'to' => $previousQuarter->copy()->endOfQuarter()->format('Y-m-d 23:59:59'),
                    ];

                case 'year':
                    // Año anterior
                    $previousYear = $now->copy()->subYear();
                    return [
                        'from' => $previousYear->copy()->startOfYear()->format('Y-m-d 00:00:00'),
                        'to' => $previousYear->copy()->endOfYear()->format('Y-m-d 23:59:59'),
                    ];

                default:
                    return null;
            }
        }

        // Si no hay filtro, no hay período anterior definido
        return null;
    }

    /**
     * Calcular el trend comparando el peso neto del período actual vs período anterior.
     * 
     * @param float $currentNetWeight Peso neto del período actual
     * @param float $previousNetWeight Peso neto del período anterior
     * @return array|null Array con 'direction' y 'percentage' o null si no se puede calcular
     */
    private function calculateTrendValue(float $currentNetWeight, float $previousNetWeight): ?array
    {
        // Si no hay datos del período anterior o el peso es 0, devolver stable
        if ($previousNetWeight == 0) {
            return [
                'direction' => 'stable',
                'percentage' => 0,
            ];
        }

        // Calcular porcentaje de cambio
        $percentage = (($currentNetWeight - $previousNetWeight) / $previousNetWeight) * 100;
        $absolutePercentage = abs($percentage);

        // Determinar dirección
        $direction = 'stable';
        if ($absolutePercentage >= 5) {
            $direction = $percentage > 0 ? 'up' : 'down';
        }

        return [
            'direction' => $direction,
            'percentage' => round($absolutePercentage, 2),
        ];
    }
}
