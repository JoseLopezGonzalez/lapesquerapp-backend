<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\TransportResource;
use App\Models\Transport;
use Illuminate\Http\Request;

class TransportController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Transport::query();

        if ($request->has('id')) {
            $query->where('id', $request->id);
        }

        if ($request->has('ids')) {
            $query->whereIn('id', $request->ids);
        }

        /* name like */
        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        /* adrdess like*/
        if ($request->has('address')) {
            $query->where('address', 'like', '%' . $request->address . '%');
        }

        /* Order by name*/
        $query->orderBy('name', 'asc');

        $perPage = $request->input('perPage', 12); // Default a 10 si no se proporciona
        return TransportResource::collection($query->paginate($perPage));
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
            'name' => 'required|string|min:3|unique:tenant.transports,name',
            'vatNumber' => 'required|string|regex:/^[A-Z0-9]{8,12}$/|unique:tenant.transports,vat_number',
            'address' => 'required|string|min:10',
            'emails' => 'required|array|min:1',
            'emails.*' => 'email',
            'ccEmails' => 'nullable|array',
            'ccEmails.*' => 'email',
        ], [
            'name.unique' => 'Ya existe un transporte con este nombre.',
            'vatNumber.unique' => 'Ya existe un transporte con este NIF/CIF.',
            'vatNumber.regex' => 'El NIF/CIF debe tener entre 8 y 12 caracteres alfanuméricos en mayúsculas.',
            'emails.required' => 'Debe proporcionar al menos un email.',
            'emails.min' => 'Debe proporcionar al menos un email.',
            'emails.*.email' => 'Uno o más emails no son válidos.',
        ]);

        $allEmails = [];

        foreach ($validated['emails'] as $email) {
            $allEmails[] = trim($email);
        }

        foreach ($validated['ccEmails'] ?? [] as $email) {
            $allEmails[] = 'CC:' . trim($email);
        }

        // Unir con salto de línea y añadir ; al final
        // emails es obligatorio, así que siempre habrá al menos un email
        $emailsText = implode(";\n", $allEmails) . ';';

        $transport = Transport::create([
            'name' => $validated['name'],
            'vat_number' => $validated['vatNumber'],
            'address' => $validated['address'],
            'emails' => $emailsText,
        ]);

        return response()->json([
            'message' => 'Transportista creado correctamente.',
            'data' => new TransportResource($transport),
        ], 201);
    }




    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $transport = Transport::findOrFail($id);

        return response()->json([
            'message' => 'Transportista obtenido con éxito',
            'data' => new TransportResource($transport),
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
        $transport = Transport::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|min:3|unique:tenant.transports,name,' . $id,
            'vatNumber' => 'required|string|regex:/^[A-Z0-9]{8,12}$/|unique:tenant.transports,vat_number,' . $id,
            'address' => 'required|string|min:10',
            'emails' => 'required|array|min:1',
            'emails.*' => 'email',
            'ccEmails' => 'nullable|array',
            'ccEmails.*' => 'email',
        ], [
            'name.unique' => 'Ya existe un transporte con este nombre.',
            'vatNumber.unique' => 'Ya existe un transporte con este NIF/CIF.',
            'vatNumber.regex' => 'El NIF/CIF debe tener entre 8 y 12 caracteres alfanuméricos en mayúsculas.',
            'emails.required' => 'Debe proporcionar al menos un email.',
            'emails.min' => 'Debe proporcionar al menos un email.',
            'emails.*.email' => 'Uno o más emails no son válidos.',
        ]);

        $allEmails = [];

        foreach ($validated['emails'] as $email) {
            $allEmails[] = trim($email);
        }

        foreach ($validated['ccEmails'] ?? [] as $email) {
            $allEmails[] = 'CC:' . trim($email);
        }

        // Unir con salto de línea y añadir ; al final
        // emails es obligatorio, así que siempre habrá al menos un email
        $emailsText = implode(";\n", $allEmails) . ';';

        $transport->update([
            'name' => $validated['name'],
            'vat_number' => $validated['vatNumber'],
            'address' => $validated['address'],
            'emails' => $emailsText,
        ]);

        return response()->json([
            'message' => 'Transportista actualizado con éxito',
            'data' => new TransportResource($transport),
        ]);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $transport = Transport::findOrFail($id);

        // Validar si el transporte está en uso antes de eliminar
        $usedInOrders = $transport->orders()->exists();
        $usedInCustomers = $transport->customers()->exists();

        if ($usedInOrders || $usedInCustomers) {
            $reasons = [];
            if ($usedInOrders) $reasons[] = 'pedidos';
            if ($usedInCustomers) $reasons[] = 'clientes';
            
            $reasonsText = implode(' y ', $reasons);
            
            return response()->json([
                'message' => 'No se puede eliminar el transporte porque está en uso',
                'details' => 'El transporte está siendo utilizado en: ' . $reasonsText,
                'userMessage' => 'No se puede eliminar el transporte porque está siendo utilizado en: ' . $reasonsText
            ], 400);
        }

        $transport->delete();

        return response()->json(['message' => 'Transporte eliminado con éxito.']);
    }

    public function destroyMultiple(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:tenant.transports,id',
        ]);

        $transports = Transport::whereIn('id', $validated['ids'])->get();
        
        // Validar si alguno de los transportes está en uso
        $inUse = [];
        foreach ($transports as $transport) {
            $usedInOrders = $transport->orders()->exists();
            $usedInCustomers = $transport->customers()->exists();
            
            if ($usedInOrders || $usedInCustomers) {
                $reasons = [];
                if ($usedInOrders) $reasons[] = 'pedidos';
                if ($usedInCustomers) $reasons[] = 'clientes';
                
                $inUse[] = [
                    'id' => $transport->id,
                    'name' => $transport->name,
                    'reasons' => implode(' y ', $reasons)
                ];
            }
        }

        if (!empty($inUse)) {
            $message = 'No se pueden eliminar algunos transportes porque están en uso: ';
            $details = array_map(function($item) {
                return $item['name'] . ' (usado en: ' . $item['reasons'] . ')';
            }, $inUse);
            
            return response()->json([
                'message' => 'No se pueden eliminar algunos transportes porque están en uso',
                'details' => implode(', ', $details),
                'userMessage' => $message . implode(', ', array_column($inUse, 'name'))
            ], 400);
        }

        Transport::whereIn('id', $validated['ids'])->delete();

        return response()->json(['message' => 'Transportes eliminados con éxito.']);
    }

    /**
     * Get all options for the transports select box.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function options()
    {
        $transports = Transport::select('id', 'name') // Selecciona solo los campos necesarios
            ->orderBy('name', 'asc') // Ordena por nombre, opcional
            ->get();

        return response()->json($transports);
    }
}
