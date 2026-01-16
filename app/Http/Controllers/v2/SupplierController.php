<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = Supplier::query();

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


        $query->orderBy('name', 'asc');

        $perPage = $request->input('perPage', 12); // Default a 10 si no se proporciona
        return SupplierResource::collection($query->paginate($perPage));
    }




    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'emails' => 'nullable|array',
            'emails.*' => 'string|email:rfc,dns|distinct',
            'ccEmails' => 'nullable|array',
            'ccEmails.*' => 'string|email:rfc,dns|distinct',
            'address' => 'nullable|string|max:1000',
            'cebo_export_type' => 'nullable|string|max:255',
            'a3erp_cebo_code' => 'nullable|string|max:255',
            'facilcom_cebo_code' => 'nullable|string|max:255',
            'facil_com_code' => 'nullable|string|max:255',
        ]);

        $allEmails = [];

        foreach ($validated['emails'] ?? [] as $email) {
            $allEmails[] = trim($email);
        }

        foreach ($validated['ccEmails'] ?? [] as $email) {
            $allEmails[] = 'CC:' . trim($email);
        }

        $validated['emails'] = count($allEmails) > 0
            ? implode(";\n", $allEmails) . ';'
            : null;

        unset($validated['ccEmails']);

        $supplier = Supplier::create($validated);

        return new SupplierResource($supplier);
    }


    public function show($id)
    {
        $supplier = Supplier::findOrFail($id);

        return response()->json([
            'message' => 'Proveedor obtenido con éxito',
            'data' => new V2SupplierResource($supplier),
        ]);
    }


    public function update(Request $request, $id)
    {
        $supplier = Supplier::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'emails' => 'nullable|array',
            'emails.*' => 'string|email:rfc,dns|distinct',
            'ccEmails' => 'nullable|array',
            'ccEmails.*' => 'string|email:rfc,dns|distinct',
            'address' => 'nullable|string|max:1000',
            'cebo_export_type' => 'nullable|string|max:255',
            'a3erp_cebo_code' => 'nullable|string|max:255',
            'facilcom_cebo_code' => 'nullable|string|max:255',
            'facil_com_code' => 'nullable|string|max:255',
        ]);

        $allEmails = [];

        foreach ($validated['emails'] ?? [] as $email) {
            $allEmails[] = trim($email);
        }

        foreach ($validated['ccEmails'] ?? [] as $email) {
            $allEmails[] = 'CC:' . trim($email);
        }

        $validated['emails'] = count($allEmails) > 0
            ? implode(";\n", $allEmails) . ';'
            : null;

        unset($validated['ccEmails']);

        $supplier->update($validated);

        return new SupplierResource($supplier);
    }


    public function destroy(string $id)
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->delete();

        return response()->json(['message' => 'Proveedor eliminado con éxito']);
    }

    public function destroyMultiple(Request $request)
    {
        $ids = $request->input('ids', []);

        if (!is_array($ids) || empty($ids)) {
            return response()->json(['message' => 'No se han proporcionado IDs válidos'], 400);
        }

        Supplier::whereIn('id', $ids)->delete();

        return response()->json(['message' => 'Proveedores eliminados con éxito']);
    }


    /**
     * Get all options for the suppliers select box.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function options()
    {
        $suppliers = Supplier::select('id', 'name') // Selecciona solo los campos necesarios
            ->orderBy('name', 'asc') // Ordena por nombre, opcional
            ->get();

        return response()->json($suppliers);
    }
}
