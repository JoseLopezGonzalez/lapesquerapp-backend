<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\EmployeeResource;
use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Employee::query();

        // Filtro por ID
        if ($request->has('id')) {
            $query->where('id', $request->id);
        }

        // Filtro por IDs
        if ($request->has('ids')) {
            $query->whereIn('id', $request->ids);
        }

        // Filtro por nombre
        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        // Filtro por UID NFC
        if ($request->has('nfc_uid')) {
            $query->where('nfc_uid', $request->nfc_uid);
        }

        // Ordenar por nombre
        $query->orderBy('name', 'asc');

        // Cargar último evento de fichaje si se solicita
        if ($request->boolean('with_last_punch')) {
            $query->with('lastPunchEvent');
        }

        $perPage = $request->input('perPage', 15);
        
        return EmployeeResource::collection($query->paginate($perPage));
    }

    /**
     * Obtener opciones de empleados (para selects, etc.)
     */
    public function options(Request $request)
    {
        $query = Employee::query();

        // Filtro por nombre (opcional)
        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        $query->orderBy('name', 'asc');

        $employees = $query->get();

        return response()->json($employees->map(function ($employee) {
            return [
                'id' => $employee->id,
                'name' => $employee->name,
                'nfcUid' => $employee->nfc_uid,
            ];
        }));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $employee = Employee::with('lastPunchEvent')->findOrFail($id);

        return response()->json([
            'message' => 'Empleado obtenido correctamente.',
            'data' => new EmployeeResource($employee),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'nfc_uid' => 'required|string|unique:employees,nfc_uid',
        ], [
            'name.required' => 'El nombre es obligatorio.',
            'nfc_uid.required' => 'El UID NFC es obligatorio.',
            'nfc_uid.unique' => 'Ya existe un empleado con este UID NFC.',
        ]);

        $employee = Employee::create($validated);

        return response()->json([
            'message' => 'Empleado creado correctamente.',
            'data' => new EmployeeResource($employee),
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $employee = Employee::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'nfc_uid' => 'sometimes|required|string|unique:employees,nfc_uid,' . $id,
        ], [
            'name.required' => 'El nombre es obligatorio.',
            'nfc_uid.required' => 'El UID NFC es obligatorio.',
            'nfc_uid.unique' => 'Ya existe otro empleado con este UID NFC.',
        ]);

        $employee->update($validated);

        return response()->json([
            'message' => 'Empleado actualizado correctamente.',
            'data' => new EmployeeResource($employee->fresh()),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $employee = Employee::findOrFail($id);
        $employee->delete();

        return response()->json([
            'message' => 'Empleado eliminado correctamente.',
        ]);
    }

    /**
     * Remove multiple resources from storage.
     */
    public function destroyMultiple(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:employees,id',
        ], [
            'ids.required' => 'Debe proporcionar un array de IDs.',
            'ids.array' => 'Los IDs deben ser un array.',
            'ids.*.integer' => 'Cada ID debe ser un número entero.',
            'ids.*.exists' => 'Uno o más IDs no existen.',
        ]);

        Employee::whereIn('id', $validated['ids'])->delete();

        return response()->json([
            'message' => 'Empleados eliminados correctamente.',
        ]);
    }
}

