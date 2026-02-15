<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
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
        $order = Order::findOrFail($request->route('orderId'));

        $this->mailerService->sendDocuments($order, $request->validated('documents'));

        return response()->json(['message' => 'Documentación enviada correctamente.']);
    }

    public function sendStandardDocumentation(int $orderId): JsonResponse
    {
        $order = Order::findOrFail($orderId);

        $this->authorize('view', $order);

        $this->mailerService->sendStandardDocuments($order);

        return response()->json(['message' => 'Documentación estándar enviada correctamente.']);
    }
}
