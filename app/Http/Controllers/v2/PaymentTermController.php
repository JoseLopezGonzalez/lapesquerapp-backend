<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\DestroyMultiplePaymentTermsRequest;
use App\Http\Requests\v2\StorePaymentTermRequest;
use App\Http\Requests\v2\UpdatePaymentTermRequest;
use App\Http\Resources\v2\PaymentTermResource;
use App\Models\PaymentTerm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentTermController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PaymentTerm::class);

        $query = PaymentTerm::query();
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

        return PaymentTermResource::collection($query->paginate($perPage))->response();
    }

    public function store(StorePaymentTermRequest $request): JsonResponse
    {
        $paymentTerm = PaymentTerm::create($request->validated());

        return response()->json([
            'message' => 'Término de pago creado correctamente.',
            'data' => new PaymentTermResource($paymentTerm),
        ], 201);
    }

    public function show(PaymentTerm $paymentTerm): JsonResponse
    {
        $this->authorize('view', $paymentTerm);

        return response()->json([
            'message' => 'Término de pago obtenido con éxito',
            'data' => new PaymentTermResource($paymentTerm),
        ]);
    }

    public function update(UpdatePaymentTermRequest $request, PaymentTerm $paymentTerm): JsonResponse
    {
        $paymentTerm->update($request->validated());

        return response()->json([
            'message' => 'Término de pago actualizado con éxito',
            'data' => new PaymentTermResource($paymentTerm),
        ]);
    }

    public function destroy(PaymentTerm $paymentTerm): JsonResponse
    {
        $this->authorize('delete', $paymentTerm);

        $usedInCustomers = $paymentTerm->customers()->exists();
        $usedInOrders = $paymentTerm->orders()->exists();

        if ($usedInCustomers || $usedInOrders) {
            $reasons = [];
            if ($usedInCustomers) {
                $reasons[] = 'clientes';
            }
            if ($usedInOrders) {
                $reasons[] = 'pedidos';
            }
            $reasonsText = implode(' y ', $reasons);

            return response()->json([
                'message' => 'No se puede eliminar el término de pago porque está en uso',
                'details' => 'El término de pago está siendo utilizado en: ' . $reasonsText,
                'userMessage' => 'No se puede eliminar el término de pago porque está siendo utilizado en: ' . $reasonsText,
            ], 400);
        }

        $paymentTerm->delete();

        return response()->json(['message' => 'Método de pago eliminado con éxito']);
    }

    public function destroyMultiple(DestroyMultiplePaymentTermsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $paymentTerms = PaymentTerm::whereIn('id', $validated['ids'])->get();

        $inUse = [];
        foreach ($paymentTerms as $paymentTerm) {
            $usedInCustomers = $paymentTerm->customers()->exists();
            $usedInOrders = $paymentTerm->orders()->exists();
            if ($usedInCustomers || $usedInOrders) {
                $reasons = [];
                if ($usedInCustomers) {
                    $reasons[] = 'clientes';
                }
                if ($usedInOrders) {
                    $reasons[] = 'pedidos';
                }
                $inUse[] = ['id' => $paymentTerm->id, 'name' => $paymentTerm->name, 'reasons' => implode(' y ', $reasons)];
            }
        }

        if (! empty($inUse)) {
            $details = array_map(fn ($item) => $item['name'] . ' (usado en: ' . $item['reasons'] . ')', $inUse);

            return response()->json([
                'message' => 'No se pueden eliminar algunos términos de pago porque están en uso',
                'details' => implode(', ', $details),
                'userMessage' => 'No se pueden eliminar algunos términos de pago porque están en uso: ' . implode(', ', array_column($inUse, 'name')),
            ], 400);
        }

        PaymentTerm::whereIn('id', $validated['ids'])->delete();

        return response()->json(['message' => 'Métodos de pago eliminados con éxito']);
    }

    public function options(): JsonResponse
    {
        $this->authorize('viewOptions', PaymentTerm::class);

        $paymentTerms = PaymentTerm::select('id', 'name')->orderBy('name', 'asc')->get();

        return response()->json($paymentTerms);
    }
}
