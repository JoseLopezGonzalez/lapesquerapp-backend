<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\DestroyMultipleSalespeopleRequest;
use App\Http\Requests\v2\IndexSalespersonRequest;
use App\Http\Requests\v2\StoreSalespersonRequest;
use App\Http\Requests\v2\UpdateSalespersonRequest;
use App\Http\Resources\v2\SalespersonResource;
use App\Models\Salesperson;
use App\Services\v2\SalespersonListService;

class SalespersonController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(IndexSalespersonRequest $request)
    {
        $this->authorize('viewAny', Salesperson::class);

        return SalespersonResource::collection(SalespersonListService::list($request));
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
    public function store(StoreSalespersonRequest $request)
    {
        $this->authorize('create', Salesperson::class);

        $validated = $request->validated();

        $allEmails = [];
        foreach ($validated['emails'] ?? [] as $email) {
            $allEmails[] = trim($email);
        }
        foreach ($validated['ccEmails'] ?? [] as $ccEmail) {
            $allEmails[] = 'CC:' . trim($ccEmail);
        }
        $validated['emails'] = count($allEmails) > 0 ? implode(";\n", $allEmails) . ';' : null;
        unset($validated['ccEmails']);

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
        $this->authorize('view', $salesperson);

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
    public function update(UpdateSalespersonRequest $request, string $id)
    {
        $salesperson = Salesperson::findOrFail($id);
        $this->authorize('update', $salesperson);

        $validated = $request->validated();

        $allEmails = [];
        foreach ($validated['emails'] ?? [] as $email) {
            $allEmails[] = trim($email);
        }
        foreach ($validated['ccEmails'] ?? [] as $ccEmail) {
            $allEmails[] = 'CC:' . trim($ccEmail);
        }
        $validated['emails'] = count($allEmails) > 0 ? implode(";\n", $allEmails) . ';' : null;
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
        $this->authorize('delete', $salesperson);

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



    public function destroyMultiple(DestroyMultipleSalespeopleRequest $request)
    {
        $this->authorize('viewAny', Salesperson::class);

        $validated = $request->validated();
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



    public function options(\Illuminate\Http\Request $request)
    {
        $user = $request->user();
        if ($user->hasRole(\App\Enums\Role::Comercial->value) && $user->salesperson) {
            return response()->json([
                ['id' => $user->salesperson->id, 'name' => $user->salesperson->name],
            ]);
        }

        $this->authorize('viewAny', Salesperson::class);

        $salespeople = Salesperson::select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json($salespeople);
    }
    
}
