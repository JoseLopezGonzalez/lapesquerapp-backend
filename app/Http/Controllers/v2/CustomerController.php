<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Customer::query();

        /* id */
        if ($request->has('id')) {
            $query->where('id', $request->id);
        }

        /* ids */
        if ($request->has('ids')) {
            $query->whereIn('id', $request->ids);
        }

        /* name like */
        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        /* vatNumber */
        if ($request->has('vatNumber')) {
            $query->where('vat_number', $request->vatNumber);
        }

        /* payentTerm where ir*/
        if ($request->has('paymentTerms')) {
            $query->whereIn('payment_term_id', $request->paymentTerms);
        }


        /* salespeople */
        if ($request->has('salespeople')) {
            $query->whereIn('salesperson_id', $request->salespeople);
        }

        /* country where ir */
        if ($request->has('countries')) {
            $query->whereIn('country_id', $request->countries);
        }

        /* order */
        $query->orderBy('name', 'asc');

        $perPage = $request->input('perPage', 10); // Default a 10 si no se proporciona
        return CustomerResource::collection($query->paginate($perPage));
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
            'name' => 'required|string|max:255',
            'vatNumber' => 'nullable|string|max:20',
            'billing_address' => 'nullable|string|max:1000',
            'shipping_address' => 'nullable|string|max:1000',
            'transportation_notes' => 'nullable|string|max:1000',
            'production_notes' => 'nullable|string|max:1000',
            'accounting_notes' => 'nullable|string|max:1000',
            'emails' => 'nullable|array',
            'emails.*' => 'string|email:rfc,dns|distinct',
            'ccEmails' => 'nullable|array',
            'ccEmails.*' => 'string|email:rfc,dns|distinct',
            'contact_info' => 'nullable|string|max:1000',
            'salesperson_id' => 'nullable|exists:tenant.salespeople,id',
            'country_id' => 'nullable|exists:tenant.countries,id',
            'payment_term_id' => 'nullable|exists:tenant.payment_terms,id',
            'transport_id' => 'nullable|exists:tenant.transports,id',
            'a3erp_code' => 'nullable|string|max:255',
            'facilcom_code' => 'nullable|string|max:255',
        ], [
            'name.required' => 'El nombre del cliente es obligatorio.',
            'name.string' => 'El nombre del cliente debe ser texto.',
            'name.max' => 'El nombre del cliente no puede tener más de 255 caracteres.',
            'vatNumber.string' => 'El NIF/CIF debe ser texto.',
            'vatNumber.max' => 'El NIF/CIF no puede tener más de 20 caracteres.',
            'billing_address.string' => 'La dirección de facturación debe ser texto.',
            'billing_address.max' => 'La dirección de facturación no puede tener más de 1000 caracteres.',
            'shipping_address.string' => 'La dirección de envío debe ser texto.',
            'shipping_address.max' => 'La dirección de envío no puede tener más de 1000 caracteres.',
            'transportation_notes.string' => 'Las notas de transporte deben ser texto.',
            'transportation_notes.max' => 'Las notas de transporte no pueden tener más de 1000 caracteres.',
            'production_notes.string' => 'Las notas de producción deben ser texto.',
            'production_notes.max' => 'Las notas de producción no pueden tener más de 1000 caracteres.',
            'accounting_notes.string' => 'Las notas contables deben ser texto.',
            'accounting_notes.max' => 'Las notas contables no pueden tener más de 1000 caracteres.',
            'emails.array' => 'Los emails deben ser una lista.',
            'emails.*.string' => 'Cada email debe ser texto.',
            'emails.*.email' => 'Uno o más emails no son válidos.',
            'emails.*.distinct' => 'No puede haber emails duplicados.',
            'ccEmails.array' => 'Los emails en copia deben ser una lista.',
            'ccEmails.*.string' => 'Cada email en copia debe ser texto.',
            'ccEmails.*.email' => 'Uno o más emails en copia no son válidos.',
            'ccEmails.*.distinct' => 'No puede haber emails en copia duplicados.',
            'contact_info.string' => 'La información de contacto debe ser texto.',
            'contact_info.max' => 'La información de contacto no puede tener más de 1000 caracteres.',
            'salesperson_id.exists' => 'El comercial seleccionado no existe.',
            'country_id.exists' => 'El país seleccionado no existe.',
            'payment_term_id.exists' => 'El término de pago seleccionado no existe.',
            'transport_id.exists' => 'El transporte seleccionado no existe.',
            'a3erp_code.string' => 'El código A3ERP debe ser texto.',
            'a3erp_code.max' => 'El código A3ERP no puede tener más de 255 caracteres.',
            'facilcom_code.string' => 'El código Facilcom debe ser texto.',
            'facilcom_code.max' => 'El código Facilcom no puede tener más de 255 caracteres.',
        ]);

        // Formatear emails
        $allEmails = [];

        foreach ($validated['emails'] ?? [] as $email) {
            $allEmails[] = trim($email) . ';';
        }

        foreach ($validated['ccEmails'] ?? [] as $ccEmail) {
            $allEmails[] = 'CC:' . trim($ccEmail) . ';';
        }

        // Reemplazar emails por texto formateado
        $validated['emails'] = !empty($allEmails) ? implode("\n", $allEmails) : null;

        // Remover ccEmails (ya están incluidos en 'emails')
        unset($validated['ccEmails']);

        // Convertir camelCase a snake_case donde sea necesario
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

        // Crear cliente
        $customer = Customer::create($data);

        // Añadir alias con Cliente Nº {cliente.id}
        $customer->alias = "Cliente Nº " . $customer->id;
        $customer->save();  // Guardamos el alias en la base de datos

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
    public function update(Request $request, string $id)
    {
        $customer = Customer::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'vatNumber' => 'nullable|string|max:20',
            'billing_address' => 'nullable|string|max:1000',
            'shipping_address' => 'nullable|string|max:1000',
            'transportation_notes' => 'nullable|string|max:1000',
            'production_notes' => 'nullable|string|max:1000',
            'accounting_notes' => 'nullable|string|max:1000',
            'emails' => 'nullable|array',
            'emails.*' => 'string|email:rfc,dns|distinct',
            'ccEmails' => 'nullable|array',
            'ccEmails.*' => 'string|email:rfc,dns|distinct',
            'contact_info' => 'nullable|string|max:1000',
            'salesperson_id' => 'nullable|exists:tenant.salespeople,id',
            'country_id' => 'nullable|exists:tenant.countries,id',
            'payment_term_id' => 'nullable|exists:tenant.payment_terms,id',
            'transport_id' => 'nullable|exists:tenant.transports,id',
            'a3erp_code' => 'nullable|string|max:255',
            'facilcom_code' => 'nullable|string|max:255',
        ], [
            'name.required' => 'El nombre del cliente es obligatorio.',
            'name.string' => 'El nombre del cliente debe ser texto.',
            'name.max' => 'El nombre del cliente no puede tener más de 255 caracteres.',
            'vatNumber.string' => 'El NIF/CIF debe ser texto.',
            'vatNumber.max' => 'El NIF/CIF no puede tener más de 20 caracteres.',
            'billing_address.string' => 'La dirección de facturación debe ser texto.',
            'billing_address.max' => 'La dirección de facturación no puede tener más de 1000 caracteres.',
            'shipping_address.string' => 'La dirección de envío debe ser texto.',
            'shipping_address.max' => 'La dirección de envío no puede tener más de 1000 caracteres.',
            'transportation_notes.string' => 'Las notas de transporte deben ser texto.',
            'transportation_notes.max' => 'Las notas de transporte no pueden tener más de 1000 caracteres.',
            'production_notes.string' => 'Las notas de producción deben ser texto.',
            'production_notes.max' => 'Las notas de producción no pueden tener más de 1000 caracteres.',
            'accounting_notes.string' => 'Las notas contables deben ser texto.',
            'accounting_notes.max' => 'Las notas contables no pueden tener más de 1000 caracteres.',
            'emails.array' => 'Los emails deben ser una lista.',
            'emails.*.string' => 'Cada email debe ser texto.',
            'emails.*.email' => 'Uno o más emails no son válidos.',
            'emails.*.distinct' => 'No puede haber emails duplicados.',
            'ccEmails.array' => 'Los emails en copia deben ser una lista.',
            'ccEmails.*.string' => 'Cada email en copia debe ser texto.',
            'ccEmails.*.email' => 'Uno o más emails en copia no son válidos.',
            'ccEmails.*.distinct' => 'No puede haber emails en copia duplicados.',
            'contact_info.string' => 'La información de contacto debe ser texto.',
            'contact_info.max' => 'La información de contacto no puede tener más de 1000 caracteres.',
            'salesperson_id.exists' => 'El comercial seleccionado no existe.',
            'country_id.exists' => 'El país seleccionado no existe.',
            'payment_term_id.exists' => 'El término de pago seleccionado no existe.',
            'transport_id.exists' => 'El transporte seleccionado no existe.',
            'a3erp_code.string' => 'El código A3ERP debe ser texto.',
            'a3erp_code.max' => 'El código A3ERP no puede tener más de 255 caracteres.',
            'facilcom_code.string' => 'El código Facilcom debe ser texto.',
            'facilcom_code.max' => 'El código Facilcom no puede tener más de 255 caracteres.',
        ]);

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

        // Recalcular alias si se requiere (opcional)
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

        // Validar si el cliente está en uso antes de eliminar
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

    public function destroyMultiple(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:tenant.customers,id',
        ], [
            'ids.required' => 'Debe proporcionar al menos un ID válido para eliminar.',
            'ids.min' => 'Debe proporcionar al menos un ID válido para eliminar.',
            'ids.*.integer' => 'Los IDs deben ser números enteros.',
            'ids.*.exists' => 'Uno o más IDs no existen.',
        ]);

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
        $customers = Customer::select('id', 'name') // Selecciona solo los campos necesarios
            ->orderBy('name', 'asc') // Ordena por nombre, opcional
            ->get();

        return response()->json($customers);
    }
}
