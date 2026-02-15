<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\DestroyMultipleEmployeesRequest;
use App\Http\Requests\v2\StoreEmployeeRequest;
use App\Http\Requests\v2\UpdateEmployeeRequest;
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
        $this->authorize('viewAny', Employee::class);

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
        $this->authorize('viewAny', Employee::class);

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
        $this->authorize('view', $employee);

        return response()->json([
            'message' => 'Empleado obtenido correctamente.',
            'data' => new EmployeeResource($employee),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEmployeeRequest $request)
    {
        $this->authorize('create', Employee::class);

        $employee = Employee::create($request->validated());

        return response()->json([
            'message' => 'Empleado creado correctamente.',
            'data' => new EmployeeResource($employee),
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEmployeeRequest $request, string $id)
    {
        $employee = Employee::findOrFail($id);
        $this->authorize('update', $employee);

        $employee->update($request->validated());

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
        $this->authorize('delete', $employee);
        $employee->delete();

        return response()->json([
            'message' => 'Empleado eliminado correctamente.',
        ]);
    }

    /**
     * Remove multiple resources from storage.
     */
    public function destroyMultiple(DestroyMultipleEmployeesRequest $request)
    {
        $this->authorize('viewAny', Employee::class);

        $ids = $request->validated()['ids'];
        foreach (Employee::whereIn('id', $ids)->get() as $employee) {
            $this->authorize('delete', $employee);
        }

        Employee::whereIn('id', $ids)->delete();

        return response()->json([
            'message' => 'Empleados eliminados correctamente.',
        ]);
    }
}

