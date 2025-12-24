<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\OrderDetailsResource;
use App\Http\Resources\v2\OrderResource;
use App\Models\Order;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

use App\Models\OrderPlannedProductDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->has('active')) {
            if ($request->active == 'true') {
                /* where status is pending or loaddate>= today at the end of the day */
                return OrderResource::collection(Order::where('status', 'pending')->orWhereDate('load_date', '>=', now())->get());
            } else {
                /* where status is finished and loaddate< today at the end of the day */
                return OrderResource::collection(Order::where('status', 'finished')->whereDate('load_date', '<', now())->get());
            }
        } else {

            $query = Order::query();
            if ($request->has('customers')) {
                $query->whereIn('customer_id', $request->customers);
                /* $query->where('customer_id', $request->customer); */
            }

            /* $request->has('id') like id*/
            if ($request->has('id')) {
                $text = $request->id;
                $query->where('id', 'like', "%{$text}%");
            }

            /* ids */
            if ($request->has('ids')) {
                $query->whereIn('id', $request->ids);
            }

            /* buyerReference */
            if ($request->has('buyerReference')) {
                $text = $request->buyerReference;
                $query->where('buyer_reference', 'like', "%{$text}%");
            }

            /* status */
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            /* loadDate */
            if ($request->has('loadDate')) {
                $loadDate = $request->input('loadDate');
                /* Check if $loadDate['start'] exists */
                if (isset($loadDate['start'])) {
                    $startDate = $loadDate['start'];
                    $startDate = date('Y-m-d 00:00:00', strtotime($startDate));
                    $query->where('load_date', '>=', $startDate);
                }
                /* Check if $loadDate['end'] exists */
                if (isset($loadDate['end'])) {
                    $endDate = $loadDate['end'];
                    $endDate = date('Y-m-d 23:59:59', strtotime($endDate));
                    $query->where('load_date', '<=', $endDate);
                }
            }

            /* entryDate */
            if ($request->has('entryDate')) {
                $entryDate = $request->input('entryDate');
                if (isset($entryDate['start'])) {
                    $startDate = $entryDate['start'];
                    $startDate = date('Y-m-d 00:00:00', strtotime($startDate));
                    $query->where('entry_date', '>=', $startDate);
                }
                if (isset($entryDate['end'])) {
                    $endDate = $entryDate['end'];
                    $endDate = date('Y-m-d 23:59:59', strtotime($endDate));
                    $query->where('entry_date', '<=', $endDate);
                }
            }

            /* transports */
            if ($request->has('transports')) {
                $query->whereIn('transport_id', $request->transports);
                /* $query->where('customer_id', $request->customer); */
            }

            /* salespeople */
            if ($request->has('salespeople')) {
                $query->whereIn('salesperson_id', $request->salespeople);
                /* $query->where('customer_id', $request->customer); */
            }

            /* palletState */
            if ($request->has('palletsState')) {
                /* if order has any pallets */
                if ($request->palletsState == 'stored') {
                    $query->whereHas('pallets', function ($q) use ($request) {
                        $q->where('status', \App\Models\Pallet::STATE_STORED);
                    });
                } else if ($request->palletsState == 'shipping') {
                    /* Solo tiene palets en el estado 3 */
                    $query->whereHas('pallets', function ($q) use ($request) {
                        $q->where('status', \App\Models\Pallet::STATE_SHIPPED);
                    });
                }
            }

            /* products - filtra pedidos que contengan algún palet con alguna caja que tenga esos productos */
            if ($request->has('products')) {
                $query->whereHas('pallets.palletBoxes.box', function ($q) use ($request) {
                    $q->whereIn('article_id', $request->products);
                });
            }

            /* species - filtra pedidos que contengan algún palet con alguna caja que tenga un producto de esas especies */
            if ($request->has('species')) {
                $query->whereHas('pallets.palletBoxes.box.product', function ($q) use ($request) {
                    $q->whereIn('species_id', $request->species);
                });
            }

            /* incoterm */
            if ($request->has('incoterm')) {
                $query->where('incoterm_id', $request->incoterm);
            }

            /* transport */
            if ($request->has('transport')) {
                $query->where('transport_id', $request->transport);
            }

            /* Sort by date desc */
            $query->orderBy('load_date', 'desc');

            $perPage = $request->input('perPage', 10); // Default a 10 si no se proporciona
            return OrderResource::collection($query->paginate($perPage));
        }
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
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer' => 'required|integer|exists:tenant.customers,id',
            'entryDate' => 'required|date',
            'loadDate' => 'required|date',
            'salesperson' => 'nullable|integer|exists:tenant.salespeople,id',
            'payment' => 'nullable|integer|exists:tenant.payment_terms,id',
            'incoterm' => 'nullable|integer|exists:tenant.incoterms,id',
            'buyerReference' => 'nullable|string',
            'transport' => 'nullable|integer|exists:tenant.transports,id',
            'truckPlate' => 'nullable|string',
            'trailerPlate' => 'nullable|string',
            'temperature' => 'nullable|string',
            'billingAddress' => 'nullable|string',
            'shippingAddress' => 'nullable|string',
            'transportationNotes' => 'nullable|string',
            'productionNotes' => 'nullable|string',
            'accountingNotes' => 'nullable|string',
            'emails' => 'nullable|array',
            'emails.*' => 'string|email:rfc,dns|distinct',
            'ccEmails' => 'nullable|array',
            'ccEmails.*' => 'string|email:rfc,dns|distinct',
            'plannedProducts' => 'nullable|array',
            'plannedProducts.*.product' => 'required|integer|exists:tenant.products,id',
            'plannedProducts.*.quantity' => 'required|numeric',
            'plannedProducts.*.boxes' => 'required|integer',
            'plannedProducts.*.unitPrice' => 'required|numeric',
            'plannedProducts.*.tax' => 'required|integer|exists:tenant.taxes,id',
        ]);

        // Formatear emails
        $allEmails = [];

        foreach ($validated['emails'] ?? [] as $email) {
            $allEmails[] = trim($email);
        }

        foreach ($validated['ccEmails'] ?? [] as $email) {
            $allEmails[] = 'CC:' . trim($email);
        }

        $formattedEmails = count($allEmails) > 0
            ? implode(";\n", $allEmails) . ';'
            : null;

        DB::beginTransaction();

        try {
            $order = Order::create([
                'customer_id' => $validated['customer'],
                'entry_date' => $validated['entryDate'],
                'load_date' => $validated['loadDate'],
                'salesperson_id' => $validated['salesperson'] ?? null,
                'payment_term_id' => $validated['payment'] ?? null,
                'incoterm_id' => $validated['incoterm'] ?? null,
                'buyer_reference' => $validated['buyerReference'] ?? null,
                'transport_id' => $validated['transport'] ?? null,
                'truck_plate' => $validated['truckPlate'] ?? null,
                'trailer_plate' => $validated['trailerPlate'] ?? null,
                'temperature' => $validated['temperature'] ?? null,
                'billing_address' => $validated['billingAddress'] ?? null,
                'shipping_address' => $validated['shippingAddress'] ?? null,
                'transportation_notes' => $validated['transportationNotes'] ?? null,
                'production_notes' => $validated['productionNotes'] ?? null,
                'accounting_notes' => $validated['accountingNotes'] ?? null,
                'emails' => $formattedEmails,
                'status' => 'pending',
            ]);

            if (!empty($validated['plannedProducts'])) {
                foreach ($validated['plannedProducts'] as $line) {
                    OrderPlannedProductDetail::create([
                        'order_id' => $order->id,
                        'product_id' => $line['product'],
                        'tax_id' => $line['tax'],
                        'quantity' => $line['quantity'],
                        'boxes' => $line['boxes'],
                        'unit_price' => $line['unitPrice'],
                        'line_base' => $line['unitPrice'] * $line['quantity'],
                        'line_total' => $line['unitPrice'] * $line['quantity'],
                    ]);
                }
            }

            DB::commit();

            // Cargar relaciones necesarias antes de retornar
            $order->load([
                'pallets.boxes.box.productionInputs',
                'pallets.boxes.box.product',
            ]);
            
            return new OrderDetailsResource($order);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear el pedido',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $order = Order::with([
            'pallets.boxes.box.productionInputs', // Cargar productionInputs para determinar disponibilidad
            'pallets.boxes.box.product', // Cargar product para los cálculos
        ])->findOrFail($id);
        
        return new OrderDetailsResource($order);
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
    public function update(Request $request, string $id)
    {
        $request->validate([
            'buyerReference' => 'sometimes|nullable|string',
            'payment' => 'sometimes|integer',
            'billingAddress' => 'sometimes|string',
            'shippingAddress' => 'sometimes|string',
            'transportationNotes' => 'sometimes|nullable|string',
            'productionNotes' => 'sometimes|nullable|string',
            'accountingNotes' => 'sometimes|nullable|string',
            'salesperson' => 'sometimes|integer',
            'emails' => 'sometimes|nullable|array',
            'emails.*' => 'string|email:rfc,dns|distinct',
            'ccEmails' => 'sometimes|nullable|array',
            'ccEmails.*' => 'string|email:rfc,dns|distinct',
            'transport' => 'sometimes|integer',
            'entryDate' => 'sometimes|date',
            'loadDate' => 'sometimes|date',
            'status' => 'sometimes|string',
            'incoterm' => 'sometimes|integer',
            'truckPlate' => 'sometimes|nullable|string',
            'trailerPlate' => 'sometimes|nullable|string',

            'temperature' => 'sometimes|nullable|numeric',
        ]);

        $order = Order::with([
            'pallets.boxes.box.productionInputs', // Cargar productionInputs para determinar disponibilidad
            'pallets.boxes.box.product', // Cargar product para los cálculos
        ])->findOrFail($id);

        if ($request->has('buyerReference')) {
            $order->buyer_reference = $request->buyerReference;
        }
        if ($request->has('payment')) {
            $order->payment_term_id = $request->payment;
        }
        if ($request->has('billingAddress')) {
            $order->billing_address = $request->billingAddress;
        }
        if ($request->has('shippingAddress')) {
            $order->shipping_address = $request->shippingAddress;
        }
        if ($request->has('transportationNotes')) {
            $order->transportation_notes = $request->transportationNotes;
        }
        if ($request->has('productionNotes')) {
            $order->production_notes = $request->productionNotes;
        }
        if ($request->has('accountingNotes')) {
            $order->accounting_notes = $request->accountingNotes;
        }
        if ($request->has('salesperson')) {
            $order->salesperson_id = $request->salesperson;
        }
        if ($request->has('transport')) {
            $order->transport_id = $request->transport;
        }
        if ($request->has('entryDate')) {
            $order->entry_date = $request->entryDate;
        }
        if ($request->has('loadDate')) {
            $order->load_date = $request->loadDate;
        }
        if ($request->has('status')) {
            $previousStatus = $order->status;
            $order->status = $request->status;
            
            // Si el pedido cambia a 'finished', cambiar todos los palets a 'shipped'
            if ($request->status === 'finished' && $previousStatus !== 'finished') {
                $order->load('pallets');
                foreach ($order->pallets as $pallet) {
                    $pallet->changeToShipped();
                }
            }
        }
        if ($request->has('incoterm')) {
            $order->incoterm_id = $request->incoterm;
        }
        if ($request->has('truckPlate')) {
            $order->truck_plate = $request->truckPlate;
        }
        if ($request->has('trailerPlate')) {
            $order->trailer_plate = $request->trailerPlate;
        }
        if ($request->has('temperature')) {
            $order->temperature = $request->temperature;
        }

        // ✅ Convertir emails y ccEmails en texto plano
        if ($request->has('emails') || $request->has('ccEmails')) {
            $allEmails = [];

            foreach ($request->emails ?? [] as $email) {
                $allEmails[] = trim($email);
            }

            foreach ($request->ccEmails ?? [] as $email) {
                $allEmails[] = 'CC:' . trim($email);
            }

            $order->emails = count($allEmails) > 0
                ? implode(";\n", $allEmails) . ';'
                : null;
        }

        $order->updated_at = now();
        $order->save();

        // Cargar relaciones necesarias antes de retornar
        $order->load([
            'pallets.boxes.box.productionInputs',
            'pallets.boxes.box.product',
        ]);

        return new OrderDetailsResource($order);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $order = Order::findOrFail($id);
        $order->delete();
        return response()->json(['message' => 'Pedido eliminado correctamente'], 200);
    }

    public function destroyMultiple(Request $request)
    {
        $ids = $request->input('ids', []);

        if (!is_array($ids) || empty($ids)) {
            return response()->json(['message' => 'No se proporcionaron IDs válidos'], 400);
        }

        Order::whereIn('id', $ids)->delete();

        return response()->json(['message' => 'Pedidos eliminados correctamente']);
    }

    /* Options */
    public function options()
    {
        $order = Order::select('id', 'id as name')
            ->orderBy('id')
            ->get();

        return response()->json($order);
    }

    /**
     * List active orders for Order Manager
     * Returns orders with status 'pending' or load_date >= today
     */
    public function active()
    {
        $orders = Order::with([
            'customer',
            'salesperson',
            'transport',
            'incoterm',
            'pallets.boxes.box.productionInputs', // Para calcular totalNetWeight y totalBoxes
            'pallets.boxes.box.product', // Para los cálculos
        ])
        ->where(function ($query) {
            $query->where('status', 'pending')
                  ->orWhereDate('load_date', '>=', now());
        })
        ->orderBy('load_date', 'desc')
        ->get();

        return OrderResource::collection($orders);
    }

    /* Active Orders Options */
    public function activeOrdersOptions()
    {
        $orders = Order::where('status', 'pending')
            ->orWhereDate('load_date', '>=', now())
            ->select('id', 'id as name', 'load_date') // 👈 Aquí añado la fecha
            ->orderBy('load_date', 'desc') // 👈 Ordenar por fecha de carga
            ->get();

        return response()->json($orders);
    }


    /* update Order status */
    public function updateStatus(Request $request, string $id)
    {
        $request->validate([
            'status' => 'required|string',
        ]);

        $order = Order::with([
            'pallets.boxes.box.productionInputs',
            'pallets.boxes.box.product',
        ])->findOrFail($id);
        
        $previousStatus = $order->status;
        $order->status = $request->status;
        $order->save();
        
        // Si el pedido cambia a 'finished', cambiar todos los palets a 'shipped'
        if ($request->status === 'finished' && $previousStatus !== 'finished') {
            foreach ($order->pallets as $pallet) {
                $pallet->changeToShipped();
            }
        }
        
        // Recargar relaciones después de actualizar
        $order->load([
            'pallets.boxes.box.productionInputs',
            'pallets.boxes.box.product',
        ]);
        
        return new OrderDetailsResource($order);
    }




    public function salesBySalesperson(Request $request)
    {
        try {
            $validated = Validator::make($request->all(), [
                'dateFrom' => 'required|date',
                'dateTo' => 'required|date',
            ])->validate();

            $dateFrom = $validated['dateFrom'] . ' 00:00:00';
            $dateTo = $validated['dateTo'] . ' 23:59:59';

            // Usar consulta directa con join para evitar problemas con accessors
            // Usar conexión tenant para multi-tenant
            $results = \DB::connection('tenant')->table('orders')
                ->join('pallets', 'pallets.order_id', '=', 'orders.id')
                ->join('pallet_boxes', 'pallet_boxes.pallet_id', '=', 'pallets.id')
                ->join('boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
                ->leftJoin('production_inputs', 'production_inputs.box_id', '=', 'boxes.id')
                ->leftJoin('salespeople', 'salespeople.id', '=', 'orders.salesperson_id')
                ->whereBetween('orders.entry_date', [$dateFrom, $dateTo])
                ->whereNull('production_inputs.id') // Solo cajas disponibles (sin production_inputs)
                ->whereIn('pallets.status', [
                    \App\Models\Pallet::STATE_REGISTERED,
                    \App\Models\Pallet::STATE_STORED,
                    \App\Models\Pallet::STATE_SHIPPED
                ])
                ->select(
                    \DB::raw('COALESCE(salespeople.name, "Sin comercial") as name'),
                    \DB::raw('SUM(boxes.net_weight) as quantity')
                )
                ->groupBy('salespeople.id', 'salespeople.name')
                ->get();

            $data = $results->map(function ($item) {
                return [
                    'name' => $item->name,
                    'quantity' => round((float)$item->quantity, 2),
                ];
            })->values();

            return response()->json($data);
        } catch (\Exception $e) {
            \Log::error('Error in salesBySalesperson: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);
            return response()->json(['error' => 'Error processing request: ' . $e->getMessage()], 500);
        }
    }

    /* Ojo, calcula la comparacion mismo rango de fechas pero un año atrás */






    public function transportChartData(Request $request)
    {
        try {
            $request->validate([
                'dateFrom' => 'required|date',
                'dateTo' => 'required|date|after_or_equal:dateFrom',
            ]);

            $from = $request->input('dateFrom');
            $to = $request->input('dateTo');

            // Usar consulta directa con join para evitar problemas con accessors
            // Usar conexión tenant para multi-tenant
            $results = \DB::connection('tenant')->table('orders')
                ->join('transports', 'transports.id', '=', 'orders.transport_id')
                ->join('pallets', 'pallets.order_id', '=', 'orders.id')
                ->join('pallet_boxes', 'pallet_boxes.pallet_id', '=', 'pallets.id')
                ->join('boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
                ->leftJoin('production_inputs', 'production_inputs.box_id', '=', 'boxes.id')
                ->whereBetween('orders.load_date', [$from, $to])
                ->whereNotNull('orders.transport_id')
                ->whereNull('production_inputs.id') // Solo cajas disponibles (sin production_inputs)
                ->whereIn('pallets.status', [
                    \App\Models\Pallet::STATE_REGISTERED,
                    \App\Models\Pallet::STATE_STORED,
                    \App\Models\Pallet::STATE_SHIPPED
                ])
                ->select(
                    'transports.name',
                    \DB::raw('SUM(boxes.net_weight) as netWeight')
                )
                ->groupBy('transports.id', 'transports.name')
                ->get();

            $result = $results->map(function ($item) {
                return [
                    'name' => $item->name ?? 'Sin transportista',
                    'netWeight' => round((float)$item->netWeight, 2),
                ];
            })->values();

            return response()->json($result);
        } catch (\Exception $e) {
            \Log::error('Error in transportChartData: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);
            return response()->json(['error' => 'Error processing request: ' . $e->getMessage()], 500);
        }
    }


















}
