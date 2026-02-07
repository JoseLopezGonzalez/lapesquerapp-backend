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
                return OrderResource::collection(
                    Order::withTotals()
                        ->with(['customer', 'salesperson', 'transport', 'incoterm'])
                        ->where('status', 'pending')
                        ->orWhereDate('load_date', '>=', now())
                        ->get()
                );
            } else {
                /* where status is finished and loaddate< today at the end of the day */
                return OrderResource::collection(
                    Order::withTotals()
                        ->with(['customer', 'salesperson', 'transport', 'incoterm'])
                        ->where('status', 'finished')
                        ->whereDate('load_date', '<', now())
                        ->get()
                );
            }
        } else {

            $query = Order::withTotals()->with(['customer', 'salesperson', 'transport', 'incoterm']);
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
        ], [
            'customer.required' => 'El cliente es obligatorio.',
            'customer.integer' => 'El cliente debe ser un número entero.',
            'customer.exists' => 'El cliente seleccionado no existe.',
            'entryDate.required' => 'La fecha de entrada es obligatoria.',
            'entryDate.date' => 'La fecha de entrada debe ser una fecha válida.',
            'loadDate.required' => 'La fecha de carga es obligatoria.',
            'loadDate.date' => 'La fecha de carga debe ser una fecha válida.',
            'salesperson.integer' => 'El comercial debe ser un número entero.',
            'salesperson.exists' => 'El comercial seleccionado no existe.',
            'payment.integer' => 'El término de pago debe ser un número entero.',
            'payment.exists' => 'El término de pago seleccionado no existe.',
            'incoterm.integer' => 'El incoterm debe ser un número entero.',
            'incoterm.exists' => 'El incoterm seleccionado no existe.',
            'buyerReference.string' => 'La referencia del comprador debe ser texto.',
            'transport.integer' => 'El transporte debe ser un número entero.',
            'transport.exists' => 'El transporte seleccionado no existe.',
            'truckPlate.string' => 'La matrícula del camión debe ser texto.',
            'trailerPlate.string' => 'La matrícula del remolque debe ser texto.',
            'temperature.string' => 'La temperatura debe ser texto.',
            'billingAddress.string' => 'La dirección de facturación debe ser texto.',
            'shippingAddress.string' => 'La dirección de envío debe ser texto.',
            'transportationNotes.string' => 'Las notas de transporte deben ser texto.',
            'productionNotes.string' => 'Las notas de producción deben ser texto.',
            'accountingNotes.string' => 'Las notas contables deben ser texto.',
            'emails.array' => 'Los emails deben ser una lista.',
            'emails.*.string' => 'Cada email debe ser texto.',
            'emails.*.email' => 'Uno o más emails no son válidos.',
            'emails.*.distinct' => 'No puede haber emails duplicados.',
            'ccEmails.array' => 'Los emails en copia deben ser una lista.',
            'ccEmails.*.string' => 'Cada email en copia debe ser texto.',
            'ccEmails.*.email' => 'Uno o más emails en copia no son válidos.',
            'ccEmails.*.distinct' => 'No puede haber emails en copia duplicados.',
            'plannedProducts.array' => 'Los productos planificados deben ser una lista.',
            'plannedProducts.*.product.required' => 'El producto es obligatorio en cada línea.',
            'plannedProducts.*.product.integer' => 'El producto debe ser un número entero.',
            'plannedProducts.*.product.exists' => 'Uno o más productos seleccionados no existen.',
            'plannedProducts.*.quantity.required' => 'La cantidad es obligatoria en cada línea.',
            'plannedProducts.*.quantity.numeric' => 'La cantidad debe ser un número.',
            'plannedProducts.*.boxes.required' => 'El número de cajas es obligatorio en cada línea.',
            'plannedProducts.*.boxes.integer' => 'El número de cajas debe ser un número entero.',
            'plannedProducts.*.unitPrice.required' => 'El precio unitario es obligatorio en cada línea.',
            'plannedProducts.*.unitPrice.numeric' => 'El precio unitario debe ser un número.',
            'plannedProducts.*.tax.required' => 'El impuesto es obligatorio en cada línea.',
            'plannedProducts.*.tax.integer' => 'El impuesto debe ser un número entero.',
            'plannedProducts.*.tax.exists' => 'Uno o más impuestos seleccionados no existen.',
        ]);

        // Validar entry_date ≤ load_date
        if ($validated['entryDate'] > $validated['loadDate']) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => [
                    'loadDate' => ['La fecha de carga debe ser mayor o igual a la fecha de entrada.']
                ],
                'userMessage' => 'La fecha de carga debe ser mayor o igual a la fecha de entrada.'
            ], 422);
        }

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
            
            return response()->json([
                'message' => 'Pedido creado correctamente.',
                'data' => new OrderDetailsResource($order),
            ], 201);

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
     * Eager loading completo (evita N+1) + select explícito para reducir datos y memoria.
     * Ref.: docs/referencia/101-Plan-Mejoras-GET-orders-id.md (Mejoras 2 y 3).
     */
    public function show(string $id)
    {
        $order = Order::select([
            'id', 'buyer_reference', 'customer_id', 'payment_term_id', 'billing_address', 'shipping_address',
            'transportation_notes', 'production_notes', 'accounting_notes', 'salesperson_id', 'emails',
            'transport_id', 'entry_date', 'load_date', 'status', 'incoterm_id', 'created_at', 'updated_at',
            'truck_plate', 'trailer_plate', 'temperature',
        ])->with([
            'customer' => fn ($q) => $q->select([
                'id', 'name', 'alias', 'vat_number', 'payment_term_id', 'billing_address', 'shipping_address',
                'transportation_notes', 'production_notes', 'accounting_notes', 'salesperson_id', 'emails',
                'contact_info', 'country_id', 'transport_id', 'a3erp_code', 'facil_com_code', 'created_at', 'updated_at',
            ]),
            'customer.payment_term' => fn ($q) => $q->select(['id', 'name', 'created_at', 'updated_at']),
            'customer.salesperson' => fn ($q) => $q->select(['id', 'name', 'emails', 'created_at', 'updated_at']),
            'customer.country' => fn ($q) => $q->select(['id', 'name', 'created_at', 'updated_at']),
            'customer.transport' => fn ($q) => $q->select(['id', 'name', 'vat_number', 'address', 'emails', 'created_at', 'updated_at']),
            'payment_term' => fn ($q) => $q->select(['id', 'name', 'created_at', 'updated_at']),
            'salesperson' => fn ($q) => $q->select(['id', 'name', 'emails', 'created_at', 'updated_at']),
            'transport' => fn ($q) => $q->select(['id', 'name', 'vat_number', 'address', 'emails', 'created_at', 'updated_at']),
            'incoterm' => fn ($q) => $q->select(['id', 'code', 'description', 'created_at', 'updated_at']),
            'plannedProductDetails' => fn ($q) => $q->select(['id', 'order_id', 'product_id', 'tax_id', 'quantity', 'boxes', 'unit_price', 'created_at', 'updated_at']),
            'plannedProductDetails.product' => fn ($q) => $q->select([
                'id', 'article_id', 'family_id', 'species_id', 'capture_zone_id', 'name', 'a3erp_code', 'facil_com_code',
                'article_gtin', 'box_gtin', 'pallet_gtin', 'fixed_weight',
            ]),
            'plannedProductDetails.product.article' => fn ($q) => $q->select(['id', 'name', 'category_id']),
            'plannedProductDetails.product.article.categoria' => fn ($q) => $q->select(['id', 'name']),
            'plannedProductDetails.product.species' => fn ($q) => $q->select(['id', 'name', 'scientific_name', 'fao', 'image']),
            'plannedProductDetails.product.captureZone' => fn ($q) => $q->select(['id', 'name']),
            'plannedProductDetails.product.family' => fn ($q) => $q->select(['id', 'name', 'description', 'category_id', 'active']),
            'plannedProductDetails.product.family.category' => fn ($q) => $q->select(['id', 'name']),
            'plannedProductDetails.tax' => fn ($q) => $q->select(['id', 'name', 'rate']),
            'incident' => fn ($q) => $q->select([
                'id', 'order_id', 'description', 'status', 'resolution_type', 'resolution_notes', 'resolved_at', 'created_at', 'updated_at',
            ]),
            'pallets' => fn ($q) => $q->select(['id', 'observations', 'status', 'order_id']),
            'pallets.boxes' => fn ($q) => $q->select(['id', 'pallet_id', 'box_id', 'lot', 'net_weight', 'article_id']),
            'pallets.boxes.box' => fn ($q) => $q->select(['id', 'article_id', 'lot', 'gs1_128', 'gross_weight', 'net_weight', 'created_at']),
            'pallets.boxes.box.productionInputs' => fn ($q) => $q->select(['id', 'box_id']),
            'pallets.boxes.box.product' => fn ($q) => $q->select([
                'id', 'article_id', 'family_id', 'species_id', 'capture_zone_id', 'name', 'a3erp_code', 'facil_com_code',
                'article_gtin', 'box_gtin', 'pallet_gtin', 'fixed_weight',
            ]),
            'pallets.boxes.box.product.article' => fn ($q) => $q->select(['id', 'name', 'category_id']),
            'pallets.boxes.box.product.article.categoria' => fn ($q) => $q->select(['id', 'name']),
            'pallets.boxes.box.product.species' => fn ($q) => $q->select(['id', 'name', 'scientific_name', 'fao', 'image']),
            'pallets.boxes.box.product.captureZone' => fn ($q) => $q->select(['id', 'name']),
            'pallets.boxes.box.product.family' => fn ($q) => $q->select(['id', 'name', 'description', 'category_id', 'active']),
            'pallets.boxes.box.product.family.category' => fn ($q) => $q->select(['id', 'name']),
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
        $validated = $request->validate([
            'buyerReference' => 'sometimes|nullable|string',
            'payment' => 'sometimes|integer|exists:tenant.payment_terms,id',
            'billingAddress' => 'sometimes|string',
            'shippingAddress' => 'sometimes|string',
            'transportationNotes' => 'sometimes|nullable|string',
            'productionNotes' => 'sometimes|nullable|string',
            'accountingNotes' => 'sometimes|nullable|string',
            'salesperson' => 'sometimes|integer|exists:tenant.salespeople,id',
            'emails' => 'sometimes|nullable|array',
            'emails.*' => 'string|email:rfc,dns|distinct',
            'ccEmails' => 'sometimes|nullable|array',
            'ccEmails.*' => 'string|email:rfc,dns|distinct',
            'transport' => 'sometimes|integer|exists:tenant.transports,id',
            'entryDate' => 'sometimes|date',
            'loadDate' => 'sometimes|date',
            'status' => 'sometimes|string|in:pending,finished,incident',
            'incoterm' => 'sometimes|integer|exists:tenant.incoterms,id',
            'truckPlate' => 'sometimes|nullable|string',
            'trailerPlate' => 'sometimes|nullable|string',
            'temperature' => 'sometimes|nullable|numeric',
        ], [
            'buyerReference.string' => 'La referencia del comprador debe ser texto.',
            'payment.integer' => 'El término de pago debe ser un número entero.',
            'payment.exists' => 'El término de pago seleccionado no existe.',
            'billingAddress.string' => 'La dirección de facturación debe ser texto.',
            'shippingAddress.string' => 'La dirección de envío debe ser texto.',
            'transportationNotes.string' => 'Las notas de transporte deben ser texto.',
            'productionNotes.string' => 'Las notas de producción deben ser texto.',
            'accountingNotes.string' => 'Las notas contables deben ser texto.',
            'salesperson.integer' => 'El comercial debe ser un número entero.',
            'salesperson.exists' => 'El comercial seleccionado no existe.',
            'emails.array' => 'Los emails deben ser una lista.',
            'emails.*.string' => 'Cada email debe ser texto.',
            'emails.*.email' => 'Uno o más emails no son válidos.',
            'emails.*.distinct' => 'No puede haber emails duplicados.',
            'ccEmails.array' => 'Los emails en copia deben ser una lista.',
            'ccEmails.*.string' => 'Cada email en copia debe ser texto.',
            'ccEmails.*.email' => 'Uno o más emails en copia no son válidos.',
            'ccEmails.*.distinct' => 'No puede haber emails en copia duplicados.',
            'transport.integer' => 'El transporte debe ser un número entero.',
            'transport.exists' => 'El transporte seleccionado no existe.',
            'entryDate.date' => 'La fecha de entrada debe ser una fecha válida.',
            'loadDate.date' => 'La fecha de carga debe ser una fecha válida.',
            'status.string' => 'El estado debe ser texto.',
            'status.in' => 'El estado del pedido no es válido. Valores permitidos: pending, finished, incident.',
            'incoterm.integer' => 'El incoterm debe ser un número entero.',
            'incoterm.exists' => 'El incoterm seleccionado no existe.',
            'truckPlate.string' => 'La matrícula del camión debe ser texto.',
            'trailerPlate.string' => 'La matrícula del remolque debe ser texto.',
            'temperature.numeric' => 'La temperatura debe ser un número.',
        ]);

        $order = Order::with([
            'pallets.boxes.box.productionInputs', // Cargar productionInputs para determinar disponibilidad
            'pallets.boxes.box.product', // Cargar product para los cálculos
        ])->findOrFail($id);

        // Validar entry_date ≤ load_date si ambas fechas están presentes
        $entryDate = $request->has('entryDate') ? $validated['entryDate'] : $order->entry_date;
        $loadDate = $request->has('loadDate') ? $validated['loadDate'] : $order->load_date;
        
        if ($entryDate && $loadDate && $entryDate > $loadDate) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => [
                    'loadDate' => ['La fecha de carga debe ser mayor o igual a la fecha de entrada.']
                ],
                'userMessage' => 'La fecha de carga debe ser mayor o igual a la fecha de entrada.'
            ], 422);
        }

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

        return response()->json([
            'message' => 'Pedido actualizado correctamente.',
            'data' => new OrderDetailsResource($order),
        ]);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $order = Order::findOrFail($id);

        // Validar si el pedido está en uso antes de eliminar
        $usedInPallets = $order->pallets()->exists();

        if ($usedInPallets) {
            return response()->json([
                'message' => 'No se puede eliminar el pedido porque está en uso',
                'details' => 'El pedido está siendo utilizado en palets',
                'userMessage' => 'No se puede eliminar el pedido porque está siendo utilizado en palets'
            ], 400);
        }

        $order->delete();
        return response()->json(['message' => 'Pedido eliminado correctamente'], 200);
    }

    public function destroyMultiple(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:tenant.orders,id',
        ], [
            'ids.required' => 'Debe proporcionar al menos un ID válido para eliminar.',
            'ids.min' => 'Debe proporcionar al menos un ID válido para eliminar.',
            'ids.*.integer' => 'Los IDs deben ser números enteros.',
            'ids.*.exists' => 'Uno o más IDs no existen.',
        ]);

        $orders = Order::whereIn('id', $validated['ids'])->get();
        
        // Validar si alguno de los pedidos está en uso
        $inUse = [];
        foreach ($orders as $order) {
            $usedInPallets = $order->pallets()->exists();
            
            if ($usedInPallets) {
                $inUse[] = [
                    'id' => $order->id,
                    'formattedId' => $order->formatted_id ?? '#' . str_pad($order->id, 5, '0', STR_PAD_LEFT),
                ];
            }
        }

        if (!empty($inUse)) {
            $message = 'No se pueden eliminar algunos pedidos porque están en uso: ';
            $details = array_map(function($item) {
                return $item['formattedId'] . ' (usado en palets)';
            }, $inUse);
            
            return response()->json([
                'message' => 'No se pueden eliminar algunos pedidos porque están en uso',
                'details' => implode(', ', $details),
                'userMessage' => $message . implode(', ', array_column($inUse, 'formattedId'))
            ], 400);
        }

        Order::whereIn('id', $validated['ids'])->delete();

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
            'status' => 'required|string|in:pending,finished,incident',
        ], [
            'status.required' => 'El estado es obligatorio.',
            'status.string' => 'El estado debe ser texto.',
            'status.in' => 'El estado del pedido no es válido. Valores permitidos: pending, finished, incident.',
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
        
        return response()->json([
            'message' => 'Estado del pedido actualizado correctamente.',
            'data' => new OrderDetailsResource($order),
        ]);
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
