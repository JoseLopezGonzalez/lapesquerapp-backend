<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\SpeciesResource;
use App\Models\Species;
use Illuminate\Http\Request;

class SpeciesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('viewAny', Species::class);

        $query = Species::query();

        /* id */
        if (request()->has('id')) {
            $query->where('id', request()->id);
        }

        /* ids */
        if (request()->has('ids')) {
            $query->whereIn('id', request()->ids);
        }

        /* name like */
        if (request()->has('name')) {
            $query->where('name', 'like', '%' . request()->name . '%');
        }


        /* fishingGears where ir */
        if (request()->has('fishingGears')) {
            $query->whereIn('fishing_gear_id', request()->fishingGears);
        }

        /* fao like */
        if (request()->has('fao')) {
            $query->where('fao', 'like', '%' . request()->fao . '%');
        }

        /* scientific name like */
        if (request()->has('scientificName')) {
            $query->where('scientific_name', 'like', '%' . request()->scientificName . '%');
        }

        /* order by name */
        $query->orderBy('name', 'asc');

        $perPage = request()->input('perPage', 10); // Default a 10 si no se proporciona
        $query->with('fishingGear');
        return SpeciesResource::collection($query->paginate($perPage));

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|min:2|unique:tenant.species,name',
            'scientificName' => 'required|string|min:2|unique:tenant.species,scientific_name',
            'fao' => ['required', 'regex:/^[A-Z]{3,5}$/', 'unique:tenant.species,fao'],
            'fishingGearId' => 'required|exists:tenant.fishing_gears,id',
            'image' => 'nullable|string',
        ], [
            'name.unique' => 'Ya existe una especie con este nombre.',
            'scientificName.unique' => 'Ya existe una especie con este nombre científico.',
            'fao.unique' => 'Ya existe una especie con este código FAO.',
        ]);

        $species = Species::create([
            'name' => $validated['name'],
            'scientific_name' => $validated['scientificName'],
            'fao' => $validated['fao'],
            'fishing_gear_id' => $validated['fishingGearId'],
            'image' => $validated['image'] ?? null,
        ]);

        return response()->json([
            'message' => 'Especie creada correctamente.',
            'data' => new SpeciesResource($species),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Species $species)
    {
        $species->load('fishingGear');
        return response()->json([
            'data' => new SpeciesResource($species),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Species $species)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|min:2|unique:tenant.species,name,' . $species->id,
            'scientificName' => 'sometimes|required|string|min:2|unique:tenant.species,scientific_name,' . $species->id,
            'fao' => ['sometimes', 'required', 'regex:/^[A-Z]{3,5}$/', 'unique:tenant.species,fao,' . $species->id],
            'fishingGearId' => 'sometimes|required|exists:tenant.fishing_gears,id',
            'image' => 'nullable|string',
        ], [
            'name.unique' => 'Ya existe una especie con este nombre.',
            'scientificName.unique' => 'Ya existe una especie con este nombre científico.',
            'fao.unique' => 'Ya existe una especie con este código FAO.',
        ]);

        $updateData = [];
        
        if (isset($validated['name'])) {
            $updateData['name'] = $validated['name'];
        }
        
        if (isset($validated['scientificName'])) {
            $updateData['scientific_name'] = $validated['scientificName'];
        }
        
        if (isset($validated['fao'])) {
            $updateData['fao'] = $validated['fao'];
        }
        
        if (isset($validated['fishingGearId'])) {
            $updateData['fishing_gear_id'] = $validated['fishingGearId'];
        }
        
        if (isset($validated['image'])) {
            $updateData['image'] = $validated['image'];
        }

        $species->update($updateData);

        return response()->json([
            'message' => 'Especie actualizada correctamente.',
            'data' => new SpeciesResource($species),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Species $species)
    {
        // Verificar si tiene productos asociados
        if ($species->products()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar la especie porque tiene productos asociados',
                'userMessage' => 'No se puede eliminar la especie porque tiene productos asociados',
            ], 400);
        }

        // Verificar si tiene producciones asociadas
        if ($species->productions()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar la especie porque tiene producciones asociadas',
                'userMessage' => 'No se puede eliminar la especie porque tiene producciones asociadas',
            ], 400);
        }

        $species->delete();
        
        return response()->json([
            'message' => 'Especie eliminada con éxito.',
        ]);
    }

    public function destroyMultiple(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:tenant.species,id',
        ], [
            'ids.required' => 'Debe proporcionar al menos un ID válido para eliminar.',
        ]);

        $species = Species::whereIn('id', $validated['ids'])->get();
        $deletedCount = 0;
        $errors = [];

        foreach ($species as $specie) {
            // Verificar si tiene productos asociados
            if ($specie->products()->count() > 0) {
                $errors[] = "Especie '{$specie->name}' no se puede eliminar porque tiene productos asociados";
                continue;
            }

            // Verificar si tiene producciones asociadas
            if ($specie->productions()->count() > 0) {
                $errors[] = "Especie '{$specie->name}' no se puede eliminar porque tiene producciones asociadas";
                continue;
            }

            $specie->delete();
            $deletedCount++;
        }

        // Construir mensajes en lenguaje natural
        $message = "Se eliminaron {$deletedCount} especies con éxito";
        $userMessage = '';
        
        if (!empty($errors)) {
            $message .= ". Errores: " . implode(', ', $errors);
            
            // Generar mensaje en lenguaje natural para el usuario
            if ($deletedCount === 0) {
                // No se eliminó ninguna
                if (count($errors) === 1) {
                    $userMessage = $errors[0];
                } else {
                    $userMessage = 'No se pudieron eliminar las especies porque tienen productos o producciones asociadas';
                }
            } else {
                // Se eliminaron algunas pero no todas
                if (count($errors) === 1) {
                    $userMessage = "Se eliminaron {$deletedCount} especies. {$errors[0]}";
                } else {
                    $userMessage = "Se eliminaron {$deletedCount} especies. Algunas no se pudieron eliminar porque tienen productos o producciones asociadas";
                }
            }
        } else {
            // Todas se eliminaron exitosamente
            $userMessage = "Se eliminaron {$deletedCount} especies con éxito";
        }

        return response()->json([
            'message' => $message,
            'userMessage' => $userMessage,
            'deletedCount' => $deletedCount,
            'errors' => $errors,
        ]);
    }
    /**
     * Get all options for the species select box.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function options()
    {
        $this->authorize('viewOptions', Species::class);

        $species = Species::select('id', 'name', 'scientific_name', 'fao')
            ->orderBy('name', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => "{$item->name} ({$item->scientific_name} - {$item->fao})"
                ];
            });

        return response()->json($species);
    }
}
