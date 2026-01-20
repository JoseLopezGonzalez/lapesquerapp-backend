<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\SalespersonResource;
use App\Models\Salesperson;
use Illuminate\Http\Request;

class SalespersonController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Salesperson::query();

        if ($request->has('id')) {
            $query->where('id', $request->id);
        }

        if ($request->has('ids')) {
            $query->whereIn('id', $request->ids);
        }

        /* mame like */
        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        $query->orderBy('name', 'asc');

        $perPage = $request->input('perPage', 10);
        return SalespersonResource::collection($query->paginate($perPage));

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
            'emails' => 'nullable|array',
            'emails.*' => 'string|email:rfc,dns|distinct',
            'ccEmails' => 'nullable|array',
            'ccEmails.*' => 'string|email:rfc,dns|distinct',
        ], [
            'name.required' => 'El nombre del comercial es obligatorio.',
            'name.string' => 'El nombre del comercial debe ser texto.',
            'name.max' => 'El nombre del comercial no puede tener más de 255 caracteres.',
            'emails.array' => 'Los emails deben ser una lista.',
            'emails.*.string' => 'Cada email debe ser texto.',
            'emails.*.email' => 'Uno o más emails no son válidos.',
            'emails.*.distinct' => 'No puede haber emails duplicados.',
            'ccEmails.array' => 'Los emails en copia deben ser una lista.',
            'ccEmails.*.string' => 'Cada email en copia debe ser texto.',
            'ccEmails.*.email' => 'Uno o más emails en copia no son válidos.',
            'ccEmails.*.distinct' => 'No puede haber emails en copia duplicados.',
        ]);

        // Combinar emails y ccEmails en un único string con ; y salto de línea
        $allEmails = [];

        foreach ($validated['emails'] ?? [] as $email) {
            $allEmails[] = trim($email);
        }

        foreach ($validated['ccEmails'] ?? [] as $ccEmail) {
            $allEmails[] = 'CC:' . trim($ccEmail);
        }

        // Convertir a string con salto de línea y punto y coma final
        $validated['emails'] = count($allEmails) > 0
            ? implode(";\n", $allEmails) . ';'
            : null;

        unset($validated['ccEmails']); // ya están incluidos

        $salesperson = Salesperson::create($validated);

        return response()->json([
            'message' => 'Comercial creado correctamente.',
            'data' => new SalespersonResource($salesperson),
        ], 201);
    }





    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $salesperson = Salesperson::findOrFail($id);

        return response()->json([
            'message' => 'Comercial obtenido con éxito',
            'data' => new SalespersonResource($salesperson),
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
        $salesperson = Salesperson::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'emails' => 'nullable|array',
            'emails.*' => 'string|email:rfc,dns|distinct',
            'ccEmails' => 'nullable|array',
            'ccEmails.*' => 'string|email:rfc,dns|distinct',
        ], [
            'name.required' => 'El nombre del comercial es obligatorio.',
            'name.string' => 'El nombre del comercial debe ser texto.',
            'name.max' => 'El nombre del comercial no puede tener más de 255 caracteres.',
            'emails.array' => 'Los emails deben ser una lista.',
            'emails.*.string' => 'Cada email debe ser texto.',
            'emails.*.email' => 'Uno o más emails no son válidos.',
            'emails.*.distinct' => 'No puede haber emails duplicados.',
            'ccEmails.array' => 'Los emails en copia deben ser una lista.',
            'ccEmails.*.string' => 'Cada email en copia debe ser texto.',
            'ccEmails.*.email' => 'Uno o más emails en copia no son válidos.',
            'ccEmails.*.distinct' => 'No puede haber emails en copia duplicados.',
        ]);

        $allEmails = [];

        foreach ($validated['emails'] ?? [] as $email) {
            $allEmails[] = trim($email);
        }

        foreach ($validated['ccEmails'] ?? [] as $ccEmail) {
            $allEmails[] = 'CC:' . trim($ccEmail);
        }

        $validated['emails'] = count($allEmails) > 0
            ? implode(";\n", $allEmails) . ';'
            : null;

        unset($validated['ccEmails']);

        $salesperson->update($validated);

        return response()->json([
            'message' => 'Comercial actualizado con éxito',
            'data' => new SalespersonResource($salesperson),
        ]);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Salesperson $salesperson)
    {
        // Validar si el comercial está en uso antes de eliminar
        $usedInCustomers = $salesperson->customers()->exists();
        $usedInOrders = $salesperson->orders()->exists();

        if ($usedInCustomers || $usedInOrders) {
            $reasons = [];
            if ($usedInCustomers) $reasons[] = 'clientes';
            if ($usedInOrders) $reasons[] = 'pedidos';
            
            $reasonsText = implode(' y ', $reasons);
            
            return response()->json([
                'message' => 'No se puede eliminar el comercial porque está en uso',
                'details' => 'El comercial está siendo utilizado en: ' . $reasonsText,
                'userMessage' => 'No se puede eliminar el comercial porque está siendo utilizado en: ' . $reasonsText
            ], 400);
        }

        $salesperson->delete();
        return response()->json(['message' => 'Comercial eliminado con éxito.']);
    }



    public function destroyMultiple(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:tenant.salespeople,id',
        ], [
            'ids.required' => 'Debe proporcionar al menos un ID válido para eliminar.',
            'ids.min' => 'Debe proporcionar al menos un ID válido para eliminar.',
            'ids.*.integer' => 'Los IDs deben ser números enteros.',
            'ids.*.exists' => 'Uno o más IDs no existen.',
        ]);

        $salespeople = Salesperson::whereIn('id', $validated['ids'])->get();
        
        // Validar si alguno de los comerciales está en uso
        $inUse = [];
        foreach ($salespeople as $salesperson) {
            $usedInCustomers = $salesperson->customers()->exists();
            $usedInOrders = $salesperson->orders()->exists();
            
            if ($usedInCustomers || $usedInOrders) {
                $reasons = [];
                if ($usedInCustomers) $reasons[] = 'clientes';
                if ($usedInOrders) $reasons[] = 'pedidos';
                
                $inUse[] = [
                    'id' => $salesperson->id,
                    'name' => $salesperson->name,
                    'reasons' => implode(' y ', $reasons)
                ];
            }
        }

        if (!empty($inUse)) {
            $message = 'No se pueden eliminar algunos comerciales porque están en uso: ';
            $details = array_map(function($item) {
                return $item['name'] . ' (usado en: ' . $item['reasons'] . ')';
            }, $inUse);
            
            return response()->json([
                'message' => 'No se pueden eliminar algunos comerciales porque están en uso',
                'details' => implode(', ', $details),
                'userMessage' => $message . implode(', ', array_column($inUse, 'name'))
            ], 400);
        }

        Salesperson::whereIn('id', $validated['ids'])->delete();

        return response()->json(['message' => 'Comerciales eliminados correctamente.']);
    }



    public function options()
    {
        $salespeople = Salesperson::select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json($salespeople);
    }
    
}
