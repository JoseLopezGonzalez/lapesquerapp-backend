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
            'name' => 'required|string|max:255|unique:tenant.payment_terms,name',
        ], [
            'name.required' => 'El nombre del término de pago es obligatorio.',
            'name.string' => 'El nombre del término de pago debe ser texto.',
            'name.max' => 'El nombre del término de pago no puede tener más de 255 caracteres.',
            'name.unique' => 'Ya existe un término de pago con este nombre.',
        ]);

        $paymentTerm = PaymentTerm::create($validated);

        return response()->json([
            'message' => 'Término de pago creado correctamente.',
            'data' => new PaymentTermResource($paymentTerm),
        ], 201);
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
            'name' => 'required|string|max:255|unique:tenant.payment_terms,name,' . $id,
        ], [
            'name.required' => 'El nombre del término de pago es obligatorio.',
            'name.string' => 'El nombre del término de pago debe ser texto.',
            'name.max' => 'El nombre del término de pago no puede tener más de 255 caracteres.',
            'name.unique' => 'Ya existe un término de pago con este nombre.',
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

        // Validar si el término de pago está en uso antes de eliminar
        $usedInCustomers = $paymentTerm->customers()->exists();
        $usedInOrders = $paymentTerm->orders()->exists();

        if ($usedInCustomers || $usedInOrders) {
            $reasons = [];
            if ($usedInCustomers) $reasons[] = 'clientes';
            if ($usedInOrders) $reasons[] = 'pedidos';
            
            $reasonsText = implode(' y ', $reasons);
            
            return response()->json([
                'message' => 'No se puede eliminar el término de pago porque está en uso',
                'details' => 'El término de pago está siendo utilizado en: ' . $reasonsText,
                'userMessage' => 'No se puede eliminar el término de pago porque está siendo utilizado en: ' . $reasonsText
            ], 400);
        }

        $paymentTerm->delete();

        return response()->json(['message' => 'Método de pago eliminado con éxito']);
    }

    public function destroyMultiple(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:tenant.payment_terms,id',
        ], [
            'ids.required' => 'Debe proporcionar al menos un ID válido para eliminar.',
            'ids.min' => 'Debe proporcionar al menos un ID válido para eliminar.',
            'ids.*.integer' => 'Los IDs deben ser números enteros.',
            'ids.*.exists' => 'Uno o más IDs no existen.',
        ]);

        $paymentTerms = PaymentTerm::whereIn('id', $validated['ids'])->get();
        
        // Validar si alguno de los términos de pago está en uso
        $inUse = [];
        foreach ($paymentTerms as $paymentTerm) {
            $usedInCustomers = $paymentTerm->customers()->exists();
            $usedInOrders = $paymentTerm->orders()->exists();
            
            if ($usedInCustomers || $usedInOrders) {
                $reasons = [];
                if ($usedInCustomers) $reasons[] = 'clientes';
                if ($usedInOrders) $reasons[] = 'pedidos';
                
                $inUse[] = [
                    'id' => $paymentTerm->id,
                    'name' => $paymentTerm->name,
                    'reasons' => implode(' y ', $reasons)
                ];
            }
        }

        if (!empty($inUse)) {
            $message = 'No se pueden eliminar algunos términos de pago porque están en uso: ';
            $details = array_map(function($item) {
                return $item['name'] . ' (usado en: ' . $item['reasons'] . ')';
            }, $inUse);
            
            return response()->json([
                'message' => 'No se pueden eliminar algunos términos de pago porque están en uso',
                'details' => implode(', ', $details),
                'userMessage' => $message . implode(', ', array_column($inUse, 'name'))
            ], 400);
        }

        PaymentTerm::whereIn('id', $validated['ids'])->delete();

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
