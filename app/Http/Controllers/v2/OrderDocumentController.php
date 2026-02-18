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
}
