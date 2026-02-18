<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Controllers\v2\Traits\HandlesChromiumConfig;
use App\Http\Requests\v2\OrderFilteredExportRequest;
use App\Enums\Role;
use App\Models\Order;
use App\Services\v2\OrderExportFilterService;
use Beganovich\Snappdf\Snappdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PDFController extends Controller
{
    use HandlesChromiumConfig;

    public function __construct(
        protected OrderExportFilterService $filterService
    ) {}

    private function generatePdf(object $entity, string $viewPath, string $fileName, array $extraData = []): StreamedResponse
    {
        $snappdf = new Snappdf();
        $html = view($viewPath, array_merge(['entity' => $entity], $extraData))->render();

        $this->configureChromium($snappdf);

        $pdf = $snappdf->setHtml($html)->generate();

        return response()->streamDownload(fn () => print $pdf, "{$fileName}.pdf", ['Content-Type' => 'application/pdf']);
    }

    private function getAuthorizedOrder(int|string $orderId): Order
    {
        $order = Order::findOrFail($orderId);
        $this->authorize('view', $order);

        return $order;
    }

    public function generateOrderSheet(int|string $orderId): StreamedResponse
    {
        $order = $this->getAuthorizedOrder($orderId);

        return $this->generatePdf($order, 'pdf.v2.orders.order_sheet', 'Hoja_de_pedido_' . $order->formattedId);
    }

    public function generateOrderSigns(int|string $orderId): StreamedResponse
    {
        $order = $this->getAuthorizedOrder($orderId);
        if (auth()->user()->hasRole(Role::Comercial->value)) {
            abort(403);
        }

        return $this->generatePdf($order, 'pdf.v2.orders.order_signs', 'Letreros_transporte_' . $order->formattedId);
    }

    public function generateOrderPackingList(int|string $orderId): StreamedResponse
    {
        $order = $this->getAuthorizedOrder($orderId);
        if (auth()->user()->hasRole(Role::Comercial->value)) {
            abort(403);
        }

        return $this->generatePdf($order, 'pdf.v2.orders.order_packing_list', 'Packing_list_' . $order->formattedId);
    }

    public function generateLoadingNote(int|string $orderId): StreamedResponse
    {
        $order = $this->getAuthorizedOrder($orderId);

        return $this->generatePdf($order, 'pdf.v2.orders.loading_note', 'Nota_de_carga_' . $order->formattedId);
    }

    public function generateRestrictedLoadingNote(int|string $orderId): StreamedResponse
    {
        $order = $this->getAuthorizedOrder($orderId);
        if (auth()->user()->hasRole(Role::Comercial->value)) {
            abort(403);
        }

        return $this->generatePdf($order, 'pdf.v2.orders.restricted_loading_note', 'Nota_de_carga_restringida_' . $order->formattedId);
    }

    public function generateOrderCMR(int|string $orderId): StreamedResponse
    {
        $order = $this->getAuthorizedOrder($orderId);
        if (auth()->user()->hasRole(Role::Comercial->value)) {
            abort(403);
        }

        return $this->generatePdf($order, 'pdf.v2.orders.CMR', 'CMR_' . $order->formattedId);
    }

    public function generateDeliveryNote(int|string $orderId): StreamedResponse
    {
        $order = $this->getAuthorizedOrder($orderId);
        if (auth()->user()->hasRole(Role::Comercial->value)) {
            abort(403);
        }

        return $this->generatePdf($order, 'pdf.v2.orders.delivery_note', 'Nota_de_entrega_' . $order->formattedId);
    }

    public function generateInvoice(int|string $orderId): StreamedResponse
    {
        $order = $this->getAuthorizedOrder($orderId);
        if (auth()->user()->hasRole(Role::Comercial->value)) {
            abort(403);
        }

        return $this->generatePdf($order, 'pdf.v2.orders.invoice', 'Factura_' . $order->formattedId);
    }

    public function generateValuedLoadingNote(int|string $orderId): StreamedResponse
    {
        $order = $this->getAuthorizedOrder($orderId);

        return $this->generatePdf($order, 'pdf.v2.orders.valued_loading_note', 'Nota_de_carga_valorada_' . $order->formattedId);
    }

    public function generateOrderConfirmation(int|string $orderId): StreamedResponse
    {
        $order = $this->getAuthorizedOrder($orderId);
        if (auth()->user()->hasRole(Role::Comercial->value)) {
            abort(403);
        }

        return $this->generatePdf($order, 'pdf.v2.orders.order_confirmation', 'Confirmacion_de_pedido_' . $order->formattedId);
    }

    public function generateTransportPickupRequest(int|string $orderId): StreamedResponse
    {
        $order = $this->getAuthorizedOrder($orderId);
        if (auth()->user()->hasRole(Role::Comercial->value)) {
            abort(403);
        }

        return $this->generatePdf($order, 'pdf.v2.orders.transport_pickup_request', 'Solicitud_de_recogida_' . $order->formattedId);
    }

    public function generateIncident(int|string $orderId): StreamedResponse
    {
        $order = $this->getAuthorizedOrder($orderId);
        if (auth()->user()->hasRole(Role::Comercial->value)) {
            abort(403);
        }

        return $this->generatePdf($order, 'pdf.v2.orders.incident', 'Incidencia_' . $order->formattedId);
    }

    public function generateOrderSheetsWithFilters(OrderFilteredExportRequest $request): StreamedResponse|\Illuminate\Http\JsonResponse
    {
        if ($request->user()->hasRole(Role::Comercial->value)) {
            abort(403);
        }

        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '300');

        $orders = $this->filterService->getFilteredOrders($request);

        if ($orders->isEmpty()) {
            return response()->json([
                'message' => 'No se encontraron pedidos con los filtros especificados',
                'userMessage' => 'No se encontraron pedidos con los filtros especificados',
            ], 404);
        }

        $snappdf = new Snappdf();
        $html = view('pdf.v2.orders.order_sheets_combined', ['orders' => $orders])->render();

        $this->configureChromium($snappdf);

        $pdf = $snappdf->setHtml($html)->generate();

        $fileName = 'Hojas_de_pedido_masivas_' . date('Y-m-d_His');

        return response()->streamDownload(fn () => print $pdf, "{$fileName}.pdf", ['Content-Type' => 'application/pdf']);
    }
}
