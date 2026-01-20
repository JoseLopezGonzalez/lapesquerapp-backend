<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\IncotermResource;
use App\Models\Incoterm;
use App\Models\Transport;
use Illuminate\Http\Request;

class IncotermController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        $query = Incoterm::query();

        if (request()->has('id')) {
            $query->where('id', request()->id);
        }

        if (request()->has('ids')) {
            $query->whereIn('id', request()->ids);
        }

        /* code like */
        if (request()->has('code')) {
            $query->where('code', 'like', '%' . request()->code . '%');
        }

        /* description */
        if (request()->has('description')) {
            $query->where('description', 'like', '%' . request()->description . '%');
        }

        /* order */
        $query->orderBy('code', 'asc');

        $perPage = request()->input('perPage', 10); // Default a 10 si no se proporciona
        return IncotermResource::collection($query->paginate($perPage));
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
            'code' => 'required|string|max:10|unique:tenant.incoterms,code',
            'description' => 'required|string|max:255',
        ], [
            'code.required' => 'El código del incoterm es obligatorio.',
            'code.string' => 'El código del incoterm debe ser texto.',
            'code.max' => 'El código del incoterm no puede tener más de 10 caracteres.',
            'code.unique' => 'Ya existe un incoterm con este código.',
            'description.required' => 'La descripción del incoterm es obligatoria.',
            'description.string' => 'La descripción del incoterm debe ser texto.',
            'description.max' => 'La descripción del incoterm no puede tener más de 255 caracteres.',
        ]);

        $incoterm = Incoterm::create($validated);

        return response()->json([
            'message' => 'Incoterm creado correctamente.',
            'data' => new IncotermResource($incoterm),
        ], 201);
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $incoterm = Incoterm::findOrFail($id);

        return response()->json([
            'message' => 'Incoterm obtenido con éxito',
            'data' => new IncotermResource($incoterm),
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
        $incoterm = Incoterm::findOrFail($id);

        $validated = $request->validate([
            'code' => 'required|string|max:10|unique:tenant.incoterms,code,' . $id,
            'description' => 'required|string|max:255',
        ], [
            'code.required' => 'El código del incoterm es obligatorio.',
            'code.string' => 'El código del incoterm debe ser texto.',
            'code.max' => 'El código del incoterm no puede tener más de 10 caracteres.',
            'code.unique' => 'Ya existe un incoterm con este código.',
            'description.required' => 'La descripción del incoterm es obligatoria.',
            'description.string' => 'La descripción del incoterm debe ser texto.',
            'description.max' => 'La descripción del incoterm no puede tener más de 255 caracteres.',
        ]);

        $incoterm->update($validated);

        return response()->json([
            'message' => 'Incoterm actualizado con éxito',
            'data' => new IncotermResource($incoterm),
        ]);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $incoterm = Incoterm::findOrFail($id);

        // Validar si el incoterm está en uso antes de eliminar
        $usedInOrders = $incoterm->orders()->exists();

        if ($usedInOrders) {
            return response()->json([
                'message' => 'No se puede eliminar el incoterm porque está en uso',
                'details' => 'El incoterm está siendo utilizado en pedidos',
                'userMessage' => 'No se puede eliminar el incoterm porque está siendo utilizado en pedidos'
            ], 400);
        }

        $incoterm->delete();

        return response()->json(['message' => 'Incoterm eliminado con éxito.']);
    }


    public function destroyMultiple(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:tenant.incoterms,id',
        ]);

        $incoterms = Incoterm::whereIn('id', $validated['ids'])->get();
        
        // Validar si alguno de los incoterms está en uso
        $inUse = [];
        foreach ($incoterms as $incoterm) {
            $usedInOrders = $incoterm->orders()->exists();
            
            if ($usedInOrders) {
                $inUse[] = [
                    'id' => $incoterm->id,
                    'code' => $incoterm->code,
                    'reasons' => 'pedidos'
                ];
            }
        }

        if (!empty($inUse)) {
            $message = 'No se pueden eliminar algunos incoterms porque están en uso: ';
            $details = array_map(function($item) {
                return $item['code'] . ' (usado en: ' . $item['reasons'] . ')';
            }, $inUse);
            
            return response()->json([
                'message' => 'No se pueden eliminar algunos incoterms porque están en uso',
                'details' => implode(', ', $details),
                'userMessage' => $message . implode(', ', array_column($inUse, 'code'))
            ], 400);
        }

        Incoterm::whereIn('id', $validated['ids'])->delete();

        return response()->json(['message' => 'Incoterms eliminados con éxito.']);
    }



    /**
     * Get all options for the incoterms select box.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function options()
    {
        $incoterms = Incoterm::select('id', 'code', 'description') // Selecciona solo los campos necesarios
            ->get();

        $incoterms = $incoterms->map(function ($incoterm) {
            return [
                'id' => $incoterm->id,
                'name' => "{$incoterm->code} - {$incoterm->description}"
            ];
        });


        return response()->json($incoterms);
    }
}
