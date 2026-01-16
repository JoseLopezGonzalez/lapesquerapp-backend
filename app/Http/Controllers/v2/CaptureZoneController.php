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
            'name' => 'required|string|min:3|max:255',
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
            'name' => 'required|string|min:3|max:255',
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
        $zone->delete();

        return response()->json(['message' => 'Zona de captura eliminada correctamente']);
    }

    public function destroyMultiple(Request $request)
    {
        $ids = $request->input('ids', []);

        if (!is_array($ids) || empty($ids)) {
            return response()->json(['message' => 'No se proporcionaron IDs válidos'], 400);
        }

        CaptureZone::whereIn('id', $ids)->delete();

        return response()->json(['message' => 'Zonas de captura eliminadas correctamente']);
    }

    /**
     * Get all options for the captureZones select box.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function options()
    {
        $captureZones = CaptureZone::select('id', 'name') // Selecciona solo los campos necesarios
            ->orderBy('name', 'asc') // Ordena por nombre, opcional
            ->get();

        return response()->json($captureZones);
    }
}
