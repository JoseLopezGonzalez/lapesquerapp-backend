<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\FishingGearResource;
use App\Models\FishingGear;
use App\Models\Transport;
use Illuminate\Http\Request;

class FishingGearController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        $query = FishingGear::query();

        if ($request->has('id')) {
            $query->where('id', $request->id);
        }

        if ($request->has('ids')) {
            $query->whereIn('id', $request->ids);
        }

        /* Name like */
        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }


        /* Order by name*/
        $query->orderBy('name', 'asc');

        $perPage = $request->input('perPage', 12); // Default a 10 si no se proporciona
        return FishingGearResource::collection($query->paginate($perPage));
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
            'name' => 'required|string|min:2|unique:tenant.fishing_gears,name',
        ], [
            'name.unique' => 'Ya existe un arte de pesca con este nombre.',
        ]);

        $fishingGear = FishingGear::create([
            'name' => $validated['name'],
        ]);

        return response()->json([
            'message' => 'Arte de pesca creado correctamente.',
            'data' => new FishingGearResource($fishingGear),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $fishingGear = FishingGear::findOrFail($id);

        return response()->json([
            'message' => 'Arte de pesca obtenido con éxito',
            'data' => new FishingGearResource($fishingGear),
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
        $fishingGear = FishingGear::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|min:2|unique:tenant.fishing_gears,name,' . $id,
        ], [
            'name.unique' => 'Ya existe un arte de pesca con este nombre.',
        ]);

        $fishingGear->update($validated);

        return response()->json([
            'message' => 'Arte de pesca actualizado con éxito',
            'data' => new FishingGearResource($fishingGear),
        ]);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $fishingGear = FishingGear::findOrFail($id);
        
        // Verificar si tiene especies asociadas
        if ($fishingGear->species()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el arte de pesca porque tiene especies asociadas',
                'userMessage' => 'No se puede eliminar el arte de pesca porque tiene especies asociadas',
            ], 400);
        }

        $fishingGear->delete();

        return response()->json([
            'message' => 'Arte de pesca eliminado con éxito.',
        ]);
    }

    public function destroyMultiple(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:tenant.fishing_gears,id',
        ], [
            'ids.required' => 'Debe proporcionar al menos un ID válido para eliminar.',
        ]);

        $fishingGears = FishingGear::whereIn('id', $validated['ids'])->get();
        $deletedCount = 0;
        $errors = [];

        foreach ($fishingGears as $fishingGear) {
            // Verificar si tiene especies asociadas
            if ($fishingGear->species()->count() > 0) {
                $errors[] = "Arte de pesca '{$fishingGear->name}' no se puede eliminar porque tiene especies asociadas";
                continue;
            }

            $fishingGear->delete();
            $deletedCount++;
        }

        // Construir mensajes en lenguaje natural
        $message = "Se eliminaron {$deletedCount} artes de pesca con éxito";
        $userMessage = '';
        
        if (!empty($errors)) {
            $message .= ". Errores: " . implode(', ', $errors);
            
            // Generar mensaje en lenguaje natural para el usuario
            if ($deletedCount === 0) {
                // No se eliminó ninguno
                if (count($errors) === 1) {
                    $userMessage = $errors[0];
                } else {
                    $userMessage = 'No se pudieron eliminar los artes de pesca porque tienen especies asociadas';
                }
            } else {
                // Se eliminaron algunos pero no todos
                if (count($errors) === 1) {
                    $userMessage = "Se eliminaron {$deletedCount} artes de pesca. {$errors[0]}";
                } else {
                    $userMessage = "Se eliminaron {$deletedCount} artes de pesca. Algunos no se pudieron eliminar porque tienen especies asociadas";
                }
            }
        } else {
            // Todos se eliminaron exitosamente
            $userMessage = "Se eliminaron {$deletedCount} artes de pesca con éxito";
        }

        return response()->json([
            'message' => $message,
            'userMessage' => $userMessage,
            'deletedCount' => $deletedCount,
            'errors' => $errors,
        ]);
    }

    /**
     * Get all options for the transports select box.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function options()
    {
        $fishingGear = FishingGear::select('id', 'name') // Selecciona solo los campos necesarios
            ->orderBy('name', 'asc') // Ordena por nombre, opcional
            ->get();

        return response()->json($fishingGear);
    }
}
