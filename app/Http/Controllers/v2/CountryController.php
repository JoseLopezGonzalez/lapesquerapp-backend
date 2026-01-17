<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\CountryResource;
use App\Models\Country;
use App\Models\Transport;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Country::query();

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

        /* Order by name*/
        $query->orderBy('name', 'asc');

        $perPage = $request->input('perPage', 12); // Default a 10 si no se proporciona
        return CountryResource::collection($query->paginate($perPage));
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
            'name' => 'required|string|min:2|max:255',
        ]);

        $country = Country::create($validated);
        return response()->json([
            'message' => 'País creado correctamente.',
            'data' => new CountryResource($country),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $country = Country::findOrFail($id);

        return response()->json([
            'message' => 'País obtenido con éxito',
            'data' => new CountryResource($country),
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
        $country = Country::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|min:2|max:255',
        ]);

        $country->update($validated);

        return response()->json([
            'message' => 'País actualizado con éxito',
            'data' => new CountryResource($country),
        ]);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $country = Country::findOrFail($id);
        $country->delete();

        return response()->json(['message' => 'País eliminado con éxito']);
    }

    public function destroyMultiple(Request $request)
    {
        $ids = $request->input('ids', []);

        if (!is_array($ids) || empty($ids)) {
            return response()->json([
                'message' => 'No se han proporcionado IDs válidos.',
                'userMessage' => 'Debe proporcionar al menos un ID válido para eliminar.'
            ], 400);
        }

        Country::whereIn('id', $ids)->delete();

        return response()->json(['message' => 'Países eliminados con éxito']);
    }

    /**
     * Get all options for the transports select box.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function options()
    {
        $country = Country::select('id', 'name') // Selecciona solo los campos necesarios
            ->orderBy('name', 'asc') // Ordena por nombre, opcional
            ->get();

        return response()->json($country);
    }
}
