<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\CaptureZoneResource;
use App\Models\CaptureZone;
use Illuminate\Http\Request;

class CaptureZoneController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', CaptureZone::class);

        $query = CaptureZone::query();

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

        /* order by name */
        $query->orderBy('name', 'asc');

        $perPage = $request->input('perPage', 12); // Default a 10 si no se proporciona
        return CaptureZoneResource::collection($query->paginate($perPage));
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
            'name' => 'required|string|min:3|max:255|unique:tenant.capture_zones,name',
        ], [
            'name.unique' => 'Ya existe una zona de captura con este nombre.',
        ]);

        $captureZone = CaptureZone::create($validated);

        return response()->json([
            'message' => 'Zona de captura creada con éxito',
            'data' => new CaptureZoneResource($captureZone),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $zone = CaptureZone::findOrFail($id);

        return response()->json([
            'message' => 'Zona de captura obtenida con éxito',
            'data' => new CaptureZoneResource($zone),
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
        $zone = CaptureZone::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|min:3|max:255|unique:tenant.capture_zones,name,' . $id,
        ], [
            'name.unique' => 'Ya existe una zona de captura con este nombre.',
        ]);

        $zone->update($validated);

        return response()->json([
            'message' => 'Zona de captura actualizada con éxito',
            'data' => new CaptureZoneResource($zone),
        ]);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $zone = CaptureZone::findOrFail($id);
        
        // Verificar si tiene productos asociados
        if ($zone->products()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar la zona de captura porque tiene productos asociados',
                'userMessage' => 'No se puede eliminar la zona de captura porque tiene productos asociados',
            ], 400);
        }

        // Verificar si tiene producciones asociadas
        if ($zone->productions()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar la zona de captura porque tiene producciones asociadas',
                'userMessage' => 'No se puede eliminar la zona de captura porque tiene producciones asociadas',
            ], 400);
        }

        $zone->delete();

        return response()->json([
            'message' => 'Zona de captura eliminada correctamente',
        ]);
    }

    public function destroyMultiple(Request $request)
    {
        $ids = $request->input('ids', []);

        if (!is_array($ids) || empty($ids)) {
            return response()->json([
                'message' => 'No se proporcionaron IDs válidos',
                'userMessage' => 'Debe proporcionar al menos un ID válido para eliminar.',
            ], 400);
        }

        $zones = CaptureZone::whereIn('id', $ids)->get();
        $deletedCount = 0;
        $errors = [];

        foreach ($zones as $zone) {
            // Verificar si tiene productos asociados
            if ($zone->products()->count() > 0) {
                $errors[] = "Zona '{$zone->name}' no se puede eliminar porque tiene productos asociados";
                continue;
            }

            // Verificar si tiene producciones asociadas
            if ($zone->productions()->count() > 0) {
                $errors[] = "Zona '{$zone->name}' no se puede eliminar porque tiene producciones asociadas";
                continue;
            }

            $zone->delete();
            $deletedCount++;
        }

        // Construir mensajes en lenguaje natural
        $message = "Se eliminaron {$deletedCount} zonas de captura con éxito";
        $userMessage = '';
        
        if (!empty($errors)) {
            $message .= ". Errores: " . implode(', ', $errors);
            
            // Generar mensaje en lenguaje natural para el usuario
            if ($deletedCount === 0) {
                // No se eliminó ninguna
                if (count($errors) === 1) {
                    $userMessage = $errors[0];
                } else {
                    $userMessage = 'No se pudieron eliminar las zonas de captura porque tienen productos o producciones asociadas';
                }
            } else {
                // Se eliminaron algunas pero no todas
                if (count($errors) === 1) {
                    $userMessage = "Se eliminaron {$deletedCount} zonas de captura. {$errors[0]}";
                } else {
                    $userMessage = "Se eliminaron {$deletedCount} zonas de captura. Algunas no se pudieron eliminar porque tienen productos o producciones asociadas";
                }
            }
        } else {
            // Todas se eliminaron exitosamente
            $userMessage = "Se eliminaron {$deletedCount} zonas de captura con éxito";
        }

        return response()->json([
            'message' => $message,
            'userMessage' => $userMessage,
            'deletedCount' => $deletedCount,
            'errors' => $errors,
        ]);
    }

    /**
     * Get all options for the captureZones select box.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function options()
    {
        $this->authorize('viewOptions', CaptureZone::class);

        $captureZones = CaptureZone::select('id', 'name') // Selecciona solo los campos necesarios
            ->orderBy('name', 'asc') // Ordena por nombre, opcional
            ->get();

        return response()->json($captureZones);
    }
}
