<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\DestroyMultipleCountriesRequest;
use App\Http\Requests\v2\StoreCountryRequest;
use App\Http\Requests\v2\UpdateCountryRequest;
use App\Http\Resources\v2\CountryResource;
use App\Models\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Country::class);

        $query = Country::query();
        if ($request->has('id')) {
            $query->where('id', $request->id);
        }
        if ($request->has('ids')) {
            $query->whereIn('id', $request->ids);
        }
        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        $query->orderBy('name', 'asc');
        $perPage = $request->input('perPage', 12);

        return CountryResource::collection($query->paginate($perPage))->response();
    }

    public function store(StoreCountryRequest $request): JsonResponse
    {
        $country = Country::create($request->validated());

        return response()->json([
            'message' => 'País creado correctamente.',
            'data' => new CountryResource($country),
        ], 201);
    }

    public function show(Country $country): JsonResponse
    {
        $this->authorize('view', $country);

        return response()->json([
            'message' => 'País obtenido con éxito',
            'data' => new CountryResource($country),
        ]);
    }

    public function update(UpdateCountryRequest $request, Country $country): JsonResponse
    {
        $country->update($request->validated());

        return response()->json([
            'message' => 'País actualizado con éxito',
            'data' => new CountryResource($country),
        ]);
    }

    public function destroy(Country $country): JsonResponse
    {
        $this->authorize('delete', $country);

        if ($country->customers()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el país porque está en uso',
                'details' => 'El país está siendo utilizado en clientes',
                'userMessage' => 'No se puede eliminar el país porque está siendo utilizado en clientes',
            ], 400);
        }

        $country->delete();

        return response()->json(['message' => 'País eliminado con éxito']);
    }

    public function destroyMultiple(DestroyMultipleCountriesRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $countries = Country::whereIn('id', $validated['ids'])->get();

        $inUse = [];
        foreach ($countries as $country) {
            if ($country->customers()->exists()) {
                $inUse[] = ['id' => $country->id, 'name' => $country->name, 'reasons' => 'clientes'];
            }
        }

        if (! empty($inUse)) {
            $details = array_map(fn ($item) => $item['name'] . ' (usado en: ' . $item['reasons'] . ')', $inUse);

            return response()->json([
                'message' => 'No se pueden eliminar algunos países porque están en uso',
                'details' => implode(', ', $details),
                'userMessage' => 'No se pueden eliminar algunos países porque están en uso: ' . implode(', ', array_column($inUse, 'name')),
            ], 400);
        }

        Country::whereIn('id', $validated['ids'])->delete();

        return response()->json(['message' => 'Países eliminados con éxito']);
    }

    public function options(): JsonResponse
    {
        $this->authorize('viewOptions', Country::class);

        $countries = Country::select('id', 'name')->orderBy('name', 'asc')->get();

        return response()->json($countries);
    }
}
