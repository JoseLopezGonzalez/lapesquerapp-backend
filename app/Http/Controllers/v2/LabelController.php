<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\DuplicateLabelRequest;
use App\Http\Requests\v2\StoreLabelRequest;
use App\Http\Requests\v2\UpdateLabelRequest;
use App\Http\Resources\v2\LabelResource;
use App\Models\Label;

class LabelController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Label::class);

        return LabelResource::collection(Label::orderBy('name')->get());
    }

    public function store(StoreLabelRequest $request)
    {
        $this->authorize('create', Label::class);

        $label = Label::create($request->validated());

        return response()->json([
            'message' => 'Etiqueta creada correctamente.',
            'data' => new LabelResource($label),
        ], 201);
    }

    public function show(Label $label)
    {
        $this->authorize('view', $label);

        return response()->json([
            'data' => new LabelResource($label),
        ]);
    }

    public function update(UpdateLabelRequest $request, Label $label)
    {
        $this->authorize('update', $label);

        $label->update($request->validated());

        return response()->json([
            'message' => 'Etiqueta actualizada correctamente.',
            'data' => new LabelResource($label),
        ]);
    }

    public function destroy(Label $label)
    {
        $this->authorize('delete', $label);

        $label->delete();

        return response()->json([
            'message' => 'Etiqueta eliminada correctamente.',
        ], 200);
    }

    public function duplicate(DuplicateLabelRequest $request, Label $label)
    {
        $this->authorize('create', Label::class);

        $defaultName = $label->name . ' (Copia)';
        $newName = $request->validated()['name'] ?? $defaultName;

        if ($newName === $defaultName && Label::where('name', $newName)->exists()) {
            $counter = 1;
            do {
                $newName = $label->name . ' (Copia ' . $counter . ')';
                $counter++;
            } while (Label::where('name', $newName)->exists() && $counter < 100);
        }

        $duplicatedLabel = Label::create([
            'name' => $newName,
            'format' => $label->format,
        ]);

        return response()->json([
            'message' => 'Etiqueta duplicada correctamente.',
            'data' => new LabelResource($duplicatedLabel),
        ], 201);
    }

    public function options()
    {
        $this->authorize('viewAny', Label::class);

        $labels = Label::orderBy('name')->get();

        return response()->json(
            $labels->map(fn ($label) => [
                'id' => $label->id,
                'name' => $label->name,
            ]),
        );
    }
}
