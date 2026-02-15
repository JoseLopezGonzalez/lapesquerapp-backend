<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\DestroyMultipleCustomersRequest;
use App\Http\Requests\v2\IndexCustomerRequest;
use App\Http\Requests\v2\StoreCustomerRequest;
use App\Http\Requests\v2\UpdateCustomerRequest;
use App\Http\Resources\v2\CustomerResource;
use App\Models\Customer;
use App\Services\v2\CustomerListService;
use App\Services\v2\CustomerOrderHistoryService;
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

        $inUse = [];
        foreach ($customers as $customer) {
            if ($customer->orders()->exists()) {
                $inUse[] = [
                    'id' => $customer->id,
                    'name' => $customer->name,
                ];
            }
        }

        if (!empty($inUse)) {
            $message = 'No se pueden eliminar algunos clientes porque están en uso: ';
            $details = array_map(fn ($item) => $item['name'] . ' (usado en pedidos)', $inUse);

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
     */
    public function options()
    {
        $this->authorize('viewAny', Customer::class);

        $customers = Customer::select('id', 'name')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json($customers);
    }

    /**
     * Obtener el historial completo de pedidos del cliente.
     */
    public function getOrderHistory(Request $request, string $id)
    {
        $customer = Customer::findOrFail($id);
        $this->authorize('view', $customer);

        $result = CustomerOrderHistoryService::getOrderHistory($customer, $request);

        return response()->json([
            'message' => 'Historial de pedidos del cliente obtenido correctamente.',
            'available_years' => $result['available_years'],
            'data' => $result['data'],
        ]);
    }
}
