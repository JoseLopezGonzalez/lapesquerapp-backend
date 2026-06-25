<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Enums\Role;
use App\Http\Requests\v2\SendCustomDocumentsRequest;
use App\Models\Order;
use App\Services\OrderMailerService;
use Illuminate\Http\JsonResponse;

class OrderDocumentController extends Controller
{
    public function __construct(
        protected OrderMailerService $mailerService
    ) {}

    public function sendCustomDocumentation(SendCustomDocumentsRequest $request): JsonResponse
    {
        if ($request->user()->hasRole(Role::Comercial->value)) {
            abort(403);
        }

        $order = Order::findOrFail($request->route('orderId'));

        $this->mailerService->sendDocuments($order, $request->validated('documents'));

        return response()->json(['message' => 'Documentación enviada correctamente.']);
    }

    public function sendStandardDocumentation(\Illuminate\Http\Request $request, int $orderId): JsonResponse
    {
        if ($request->user()->hasRole(Role::Comercial->value)) {
            abort(403);
        }

        $order = Order::findOrFail($orderId);

        $this->authorize('view', $order);

        $this->mailerService->sendStandardDocuments($order);

        return response()->json(['message' => 'Documentación estándar enviada correctamente.']);
    }

    public function sendMaquiladorDocumentation(\Illuminate\Http\Request $request, int $orderId): JsonResponse
    {
        if ($request->user()->hasRole(Role::Comercial->value)) {
            abort(403);
        }

        $order = Order::with('externalProcessor')->findOrFail($orderId);

        $this->authorize('view', $order);

        if (! $order->external_processor_id) {
            return response()->json([
                'message' => 'El pedido no tiene un transformador externo asignado.',
                'userMessage' => 'Asigna un maquilador al pedido antes de enviar su documentación.',
            ], 422);
        }

        if (empty($order->externalProcessor->emailsArray)) {
            return response()->json([
                'message' => 'El maquilador no tiene emails configurados.',
                'userMessage' => 'El transformador externo asignado no tiene ninguna dirección de email. Añade emails en su ficha antes de enviar.',
            ], 422);
        }

        $this->mailerService->sendMaquiladorDocuments($order);

        return response()->json(['message' => 'Documentación enviada al maquilador correctamente.']);
    }
}
