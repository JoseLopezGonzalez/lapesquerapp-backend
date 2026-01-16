<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\PaymentTermResource;
use App\Models\PaymentTerm;
use App\Models\Transport;
use Illuminate\Http\Request;

class PaymentTermController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = PaymentTerm::query();

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
        return PaymentTermResource::collection($query->paginate($perPage));
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
            'name' => 'required|string|max:255',
        ]);

        $paymentTerm = PaymentTerm::create($validated);

        return new PaymentTermResource($paymentTerm);
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $paymentTerm = PaymentTerm::findOrFail($id);

        return response()->json([
            'message' => 'Término de pago obtenido con éxito',
            'data' => new PaymentTermResource($paymentTerm),
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
        $paymentTerm = PaymentTerm::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $paymentTerm->update($validated);

        return response()->json([
            'message' => 'Término de pago actualizado con éxito',
            'data' => new PaymentTermResource($paymentTerm),
        ]);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $paymentTerm = PaymentTerm::findOrFail($id);
        $paymentTerm->delete();

        return response()->json(['message' => 'Método de pago eliminado con éxito']);
    }

    public function destroyMultiple(Request $request)
    {
        $ids = $request->input('ids', []);

        if (!is_array($ids) || empty($ids)) {
            return response()->json(['message' => 'No se han proporcionado IDs válidos'], 400);
        }

        PaymentTerm::whereIn('id', $ids)->delete();

        return response()->json(['message' => 'Métodos de pago eliminados con éxito']);
    }



    /**
     * Get all options for the transports select box.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function options()
    {
        $paymentTerm = PaymentTerm::select('id', 'name') // Selecciona solo los campos necesarios
            ->orderBy('name', 'asc') // Ordena por nombre, opcional
            ->get();

        return response()->json($paymentTerm);
    }
}
