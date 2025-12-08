<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\v1\OrderDetailsResource;
use App\Http\Resources\v1\OrderResource;
use App\Models\Order;
use App\Models\Pallet;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

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
                $orders = Order::with([
                    'customer',
                    'customer.payment_term',
                    'customer.salesperson',
                    'customer.country',
                    'customer.transport',
                    'payment_term',
                    'salesperson',
                    'transport',
                    'incoterm',
                ])->where('status', 'pending')->orWhereDate('load_date', '>=', now())->get();
                return OrderResource::collection($orders);
            } else {
                /* where status is finished and loaddate< today at the end of the day */
                $orders = Order::with([
                    'customer',
                    'customer.payment_term',
                    'customer.salesperson',
                    'customer.country',
                    'customer.transport',
                    'payment_term',
                    'salesperson',
                    'transport',
                    'incoterm',
                ])->where('status', 'finished')->whereDate('load_date', '<', now())->get();
                return OrderResource::collection($orders);
            }
        } else {

            /* $request->customers is a array of Customers Id ¿hay que utilizar Where In? */

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

            if($request->has('entryDate')){
                $entryDate = $request->input('entryDate');
                if(isset($entryDate['start'])){
                    $startDate = $entryDate['start'];
                    $startDate = date('Y-m-d 00:00:00', strtotime($startDate));
                    $query->where('entry_date', '>=', $startDate);
                }
                if(isset($entryDate['end'])){
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
                
                if($request->palletsState == 'stored'){
                    $query->whereHas('pallets', function ($q) use ($request) {
                        $q->where('status', Pallet::STATE_STORED);
                    });
                }else if ($request->palletsState == 'shipping'){
                    /* Solo tiene palets en el estado 3 */
                    $query->whereHas('pallets', function ($q) use ($request) {
                        $q->where('status', Pallet::STATE_SHIPPED);
                    });
                    
                }
            }

            

            /* Sort by date desc */
            $query->orderBy('load_date', 'desc');
            
            // Cargar relaciones necesarias para OrderResource
            $query->with([
                'customer',
                'customer.payment_term',
                'customer.salesperson',
                'customer.country',
                'customer.transport',
                'payment_term',
                'salesperson',
                'transport',
                'incoterm',
            ]);

            $perPage = $request->input('perPage', 12); // Default a 10 si no se proporciona
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

        $validator = Validator::make($request->all(), [
            'buyerReference' => 'sometimes|nullable|string',
            'customer.id' => 'required | integer',
            'paymentTerm.id' => 'required | integer',
            'billingAddress' => 'required | string',
            'shippingAddress' => 'required | string',
            'transportationNotes' => 'sometimes|nullable|string',
            'productionNotes' => 'sometimes|nullable|string',
            'accountingNotes' => 'sometimes|nullable|string',
            'salesperson.id' => 'required | integer',
            'emails' => 'sometimes|nullable|string',/* comprobar */
            'transport.id' => 'required | integer',
            /* Incoterm */
            'incoterm.id' => 'required | integer',
            'entryDate' => 'required | date',
            'loadDate' => 'required | date'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422); // Código de estado 422 - Unprocessable Entity
        }

        $order = new Order;
        if ($request->has('buyerReference')) {
            $order->buyer_reference = $request->buyerReference;
        }
        $order->customer_id = $request->customer['id'];
        $order->payment_term_id = $request->paymentTerm['id'];
        $order->billing_address = $request->billingAddress;
        $order->shipping_address = $request->shippingAddress;
        if ($request->has('transportationNotes')) {
            $order->transportation_notes = $request->transportationNotes;
        }
        if ($request->has('productionNotes')) {
            $order->production_notes = $request->productionNotes;
        }
        if ($request->has('accountingNotes')) {
            $order->accounting_notes = $request->accountingNotes;
        }
        $order->salesperson_id = $request->salesperson['id'];
        if ($request->has('emails')) {
            $order->emails = $request->emails;
        }
        $order->transport_id = $request->transport['id'];
        $order->incoterm_id = $request->incoterm['id'];
        $order->entry_date = $request->entryDate;
        $order->load_date = $request->loadDate;
        $order->status = 'pending';
        $order->save();
        
        // Recargar con relaciones para el resource
        $order->load([
            'customer',
            'customer.payment_term',
            'customer.salesperson',
            'customer.country',
            'customer.transport',
            'payment_term',
            'salesperson',
            'transport',
            'incoterm',
            'pallets.boxes.box.product.article.categoria',
            'pallets.boxes.box.product.family.category',
            'pallets.boxes.box.product.species',
            'pallets.boxes.box.product.captureZone',
            'pallets.boxes.box.article.categoria',
            'pallets.boxes.box.productionInputs',
        ]);
        
        return new OrderDetailsResource($order);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $order = Order::with([
            'customer',
            'customer.payment_term',
            'customer.salesperson',
            'customer.country',
            'customer.transport',
            'payment_term',
            'salesperson',
            'transport',
            'incoterm',
            'pallets.boxes.box.product.article.categoria',
            'pallets.boxes.box.product.family.category',
            'pallets.boxes.box.product.species',
            'pallets.boxes.box.product.captureZone',
            'pallets.boxes.box.article.categoria',
            'pallets.boxes.box.productionInputs',
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
            'paymentTerm.id' => 'sometimes | integer',
            'billingAddress' => 'sometimes | string',
            'shippingAddress' => 'sometimes | string',
            'transportationNotes' => 'sometimes|nullable|string',
            'productionNotes' => 'sometimes|nullable|string',
            'accountingNotes' => 'sometimes|nullable|string',
            'salesperson.id' => 'sometimes | integer',
            'emails' => 'sometimes|nullable|string',/* comprobar */
            'transport.id' => 'sometimes | integer',
            'entryDate' => 'sometimes | date',
            'loadDate' => 'sometimes | date',
            'status' => 'sometimes | string',
            /* Incoterm */
            'incoterm.id' => 'sometimes | integer',
        ]);

        $order = Order::findOrFail($id);
        if ($request->has('buyerReference')) {
            $order->buyer_reference = $request->buyerReference;
        }
        if ($request->has('paymentTerm.id')) {
            $order->payment_term_id = $request->paymentTerm['id'];
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
        if ($request->has('salesperson.id')) {
            $order->salesperson_id = $request->salesperson['id'];
        }
        if ($request->has('emails')) {
            $order->emails = $request->emails;
        }
        if ($request->has('transport.id')) {
            $order->transport_id = $request->transport['id'];
        }
        if ($request->has('entryDate')) {
            $order->entry_date = $request->entryDate;
        }
        if ($request->has('loadDate')) {
            $order->load_date = $request->loadDate;
        }
        if ($request->has('status')) {
            $order->status = $request->status;
        }
        if ($request->has('incoterm.id')) {
            $order->incoterm_id = $request->incoterm['id'];
        }

        $order->updated_at = now();
        $order->save();
        
        // Recargar con relaciones para el resource
        $order->load([
            'customer',
            'customer.payment_term',
            'customer.salesperson',
            'customer.country',
            'customer.transport',
            'payment_term',
            'salesperson',
            'transport',
            'incoterm',
            'pallets.boxes.box.product.article.categoria',
            'pallets.boxes.box.product.family.category',
            'pallets.boxes.box.product.species',
            'pallets.boxes.box.product.captureZone',
            'pallets.boxes.box.article.categoria',
            'pallets.boxes.box.productionInputs',
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
        return response()->json(['message' => 'Palet eliminado correctamente'], 200);
    }

   
}
