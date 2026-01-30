<?php


namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Controllers\v2\Traits\HandlesChromiumConfig;
use App\Models\Order;
use Beganovich\Snappdf\Snappdf;
use Illuminate\Http\Request;



class PDFController extends Controller
{
    use HandlesChromiumConfig;

    /* v2 */

    /**
     * Método genérico para generar PDFs de cualquier entidad
     *
     * @param string $modelClass Modelo de la entidad (Ej: Order::class)
     * @param int $entityId ID de la entidad (Ej: orderId, invoiceId, etc.)
     * @param string $viewPath Ruta de la vista Blade (Ej: 'pdf.v2.orders.order_sheet')
     * @param string $fileName Nombre del archivo PDF (Ej: 'Hoja_de_pedido')
     * @param array $extraData Datos adicionales a pasar a la vista
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    private function generatePdf($entity, $viewPath, $fileName, $extraData = [])
    {
        $snappdf = new Snappdf();
        $html = view($viewPath, array_merge(['entity' => $entity], $extraData))->render();
        
        // Configure Chromium using centralized configuration
        $this->configureChromium($snappdf);

        // Generar PDF
        $pdf = $snappdf->setHtml($html)->generate();

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, "{$fileName}.pdf", ['Content-Type' => 'application/pdf']);
    }

    /* Métodos específicos para cada tipo de documento */

    public function generateOrderSheet($orderId)
    {
        $order = Order::findOrFail($orderId);
        $fileName = 'Hoja_de_pedido_' . $order->formattedId;
        return $this->generatePdf($order, 'pdf.v2.orders.order_sheet', $fileName);
    }

    public function generateOrderSigns($orderId)
    {
        $order = Order::findOrFail($orderId);
        $fileName = 'Letreros_transporte_' . $order->formattedId;
        return $this->generatePdf($order, 'pdf.v2.orders.order_signs', $fileName);
    }

    public function generateOrderPackingList($orderId)
    {
        $order = Order::findOrFail($orderId);
        $fileName = 'Packing_list_' . $order->formattedId;
        return $this->generatePdf($order, 'pdf.v2.orders.order_packing_list', $fileName);
    }

    public function generateLoadingNote($orderId)
    {
        $order = Order::findOrFail($orderId);
        $fileName = 'Nota_de_carga_' . $order->formattedId;
        return $this->generatePdf($order, 'pdf.v2.orders.loading_note', $fileName);
    }

    public function generateRestrictedLoadingNote($orderId)
    {
        $order = Order::findOrFail($orderId);
        $fileName = 'Nota_de_carga_restringida_' . $order->formattedId;
        return $this->generatePdf($order, 'pdf.v2.orders.restricted_loading_note', $fileName);
    }

    public function generateOrderCMR($orderId)
    {
        $order = Order::findOrFail($orderId);
        $fileName = 'CMR_' . $order->formattedId;
        return $this->generatePdf($order, 'pdf.v2.orders.CMR', $fileName);
    }

    public function generateDeliveryNote($orderId)
    {
        $order = Order::findOrFail($orderId);
        $fileName = 'Nota_de_entrega_' . $order->formattedId;
        return $this->generatePdf($order, 'pdf.v2.orders.delivery_note', $fileName);
    }

    public function generateInvoice($orderId)
    {
        $order = Order::findOrFail($orderId);
        $fileName = 'Factura_' . $order->formattedId;
        return $this->generatePdf($order, 'pdf.v2.orders.invoice', $fileName);
    }

    /* valued Delivery Note */
    public function generateValuedLoadingNote($orderId)
    {
        $order = Order::findOrFail($orderId);
        $fileName = 'Nota_de_carga_valorada_' . $order->formattedId;
        return $this->generatePdf($order, 'pdf.v2.orders.valued_loading_note', $fileName);
    }

    /* order confirmation */
    public function generateOrderConfirmation($orderId)
    {
        $order = Order::findOrFail($orderId);
        $fileName = 'Confirmacion_de_pedido_' . $order->formattedId;
        return $this->generatePdf($order, 'pdf.v2.orders.order_confirmation', $fileName);
    }

    /* transport pickup reques */
    public function generateTransportPickupRequest($orderId)
    {
        $order = Order::findOrFail($orderId);
        $fileName = 'Solicitud_de_recogida_' . $order->formattedId;
        return $this->generatePdf($order, 'pdf.v2.orders.transport_pickup_request', $fileName);
    }

    public function generateIncident($orderId)
    {
        $order = Order::findOrFail($orderId);
        $fileName = 'Incidencia_' . $order->formattedId;
        return $this->generatePdf($order, 'pdf.v2.orders.incident', $fileName);
    }

    /**
     * Valida y sanitiza un array de enteros para usar en whereIn()
     * Previene SQL injection asegurando que solo se usen arrays de enteros válidos
     * 
     * @param mixed $value Valor del request
     * @return array Array de enteros válidos, o array vacío si no es válido
     */
    private function sanitizeIntegerArray($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_filter(
            array_map('intval', $value),
            fn($item) => $item > 0
        );
    }

    /**
     * Genera hojas de pedidos de forma masiva con los mismos filtros que las exportaciones Excel
     * 
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\JsonResponse
     */
    public function generateOrderSheetsWithFilters(Request $request)
    {
        // Aplicar límites de memoria y tiempo similares a las exportaciones Excel
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '300');

        $query = Order::query();

        // Aplicar los mismos filtros que ExcelController
        if ($request->has('active')) {
            if ($request->active == 'true') {
                $query->where(function ($q) {
                    $q->where('status', 'pending')
                        ->orWhereDate('load_date', '>=', now());
                });
            } else {
                $query->where('status', 'finished')
                    ->whereDate('load_date', '<', now());
            }
        }

        if ($request->has('customers')) {
            $customers = $this->sanitizeIntegerArray($request->customers);
            if (!empty($customers)) {
                $query->whereIn('customer_id', $customers);
            }
        }

        if ($request->has('id')) {
            $query->where('id', 'like', '%' . $request->id . '%');
        }

        if ($request->has('ids')) {
            $ids = $this->sanitizeIntegerArray($request->ids);
            if (!empty($ids)) {
                $query->whereIn('id', $ids);
            }
        }

        if ($request->has('buyerReference')) {
            $query->where('buyer_reference', 'like', '%' . $request->buyerReference . '%');
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('loadDate')) {
            $loadDate = $request->loadDate;
            if (isset($loadDate['start'])) {
                $query->where('load_date', '>=', date('Y-m-d 00:00:00', strtotime($loadDate['start'])));
            }
            if (isset($loadDate['end'])) {
                $query->where('load_date', '<=', date('Y-m-d 23:59:59', strtotime($loadDate['end'])));
            }
        }

        if ($request->has('entryDate')) {
            $entryDate = $request->entryDate;
            if (isset($entryDate['start'])) {
                $query->where('entry_date', '>=', date('Y-m-d 00:00:00', strtotime($entryDate['start'])));
            }
            if (isset($entryDate['end'])) {
                $query->where('entry_date', '<=', date('Y-m-d 23:59:59', strtotime($entryDate['end'])));
            }
        }

        if ($request->has('transports')) {
            $transports = $this->sanitizeIntegerArray($request->transports);
            if (!empty($transports)) {
                $query->whereIn('transport_id', $transports);
            }
        }

        if ($request->has('salespeople')) {
            $salespeople = $this->sanitizeIntegerArray($request->salespeople);
            if (!empty($salespeople)) {
                $query->whereIn('salesperson_id', $salespeople);
            }
        }

        if ($request->has('palletsState')) {
            if ($request->palletsState == 'stored') {
                $query->whereHas('pallets', fn($q) => $q->where('status', \App\Models\Pallet::STATE_STORED));
            } elseif ($request->palletsState == 'shipping') {
                $query->whereHas('pallets', fn($q) => $q->where('status', \App\Models\Pallet::STATE_SHIPPED));
            }
        }

        if ($request->has('incoterm')) {
            $query->where('incoterm_id', $request->incoterm);
        }

        if ($request->has('transport')) {
            $query->where('transport_id', $request->transport);
        }

        // Filtros adicionales del OrderController index
        if ($request->has('products')) {
            $products = $this->sanitizeIntegerArray($request->products);
            if (!empty($products)) {
                $query->whereHas('pallets.palletBoxes.box', function ($q) use ($products) {
                    $q->whereIn('article_id', $products);
                });
            }
        }

        if ($request->has('species')) {
            $species = $this->sanitizeIntegerArray($request->species);
            if (!empty($species)) {
                $query->whereHas('pallets.palletBoxes.box.product', function ($q) use ($species) {
                    $q->whereIn('species_id', $species);
                });
            }
        }

        $query->orderBy('load_date', 'desc');

        $orders = $query->get();

        if ($orders->isEmpty()) {
            return response()->json([
                'message' => 'No se encontraron pedidos con los filtros especificados',
                'userMessage' => 'No se encontraron pedidos con los filtros especificados'
            ], 404);
        }

        // Generar PDF combinado
        $snappdf = new Snappdf();
        $html = view('pdf.v2.orders.order_sheets_combined', ['orders' => $orders])->render();
        
        // Configure Chromium using centralized configuration
        $this->configureChromium($snappdf);

        // Generar PDF
        $pdf = $snappdf->setHtml($html)->generate();

        $fileName = 'Hojas_de_pedido_masivas_' . date('Y-m-d_His');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, "{$fileName}.pdf", ['Content-Type' => 'application/pdf']);
    }
}
