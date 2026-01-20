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
            'name' => 'required|string|min:2|max:255|unique:tenant.countries,name',
        ], [
            'name.required' => 'El nombre del país es obligatorio.',
            'name.string' => 'El nombre del país debe ser texto.',
            'name.min' => 'El nombre del país debe tener al menos 2 caracteres.',
            'name.max' => 'El nombre del país no puede tener más de 255 caracteres.',
            'name.unique' => 'Ya existe un país con este nombre.',
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
            'name' => 'required|string|min:2|max:255|unique:tenant.countries,name,' . $id,
        ], [
            'name.required' => 'El nombre del país es obligatorio.',
            'name.string' => 'El nombre del país debe ser texto.',
            'name.min' => 'El nombre del país debe tener al menos 2 caracteres.',
            'name.max' => 'El nombre del país no puede tener más de 255 caracteres.',
            'name.unique' => 'Ya existe un país con este nombre.',
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

        // Validar si el país está en uso antes de eliminar
        $usedInCustomers = $country->customers()->exists();

        if ($usedInCustomers) {
            return response()->json([
                'message' => 'No se puede eliminar el país porque está en uso',
                'details' => 'El país está siendo utilizado en clientes',
                'userMessage' => 'No se puede eliminar el país porque está siendo utilizado en clientes'
            ], 400);
        }

        $country->delete();

        return response()->json(['message' => 'País eliminado con éxito']);
    }

    public function destroyMultiple(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:tenant.countries,id',
        ], [
            'ids.required' => 'Debe proporcionar al menos un ID válido para eliminar.',
            'ids.min' => 'Debe proporcionar al menos un ID válido para eliminar.',
            'ids.*.integer' => 'Los IDs deben ser números enteros.',
            'ids.*.exists' => 'Uno o más IDs no existen.',
        ]);

        $countries = Country::whereIn('id', $validated['ids'])->get();
        
        // Validar si alguno de los países está en uso
        $inUse = [];
        foreach ($countries as $country) {
            $usedInCustomers = $country->customers()->exists();
            
            if ($usedInCustomers) {
                $inUse[] = [
                    'id' => $country->id,
                    'name' => $country->name,
                    'reasons' => 'clientes'
                ];
            }
        }

        if (!empty($inUse)) {
            $message = 'No se pueden eliminar algunos países porque están en uso: ';
            $details = array_map(function($item) {
                return $item['name'] . ' (usado en: ' . $item['reasons'] . ')';
            }, $inUse);
            
            return response()->json([
                'message' => 'No se pueden eliminar algunos países porque están en uso',
                'details' => implode(', ', $details),
                'userMessage' => $message . implode(', ', array_column($inUse, 'name'))
            ], 400);
        }

        Country::whereIn('id', $validated['ids'])->delete();

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
