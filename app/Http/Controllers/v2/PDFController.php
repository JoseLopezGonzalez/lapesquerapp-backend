<?php


namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Controllers\v2\Traits\HandlesChromiumConfig;
use App\Models\Order;
use Beganovich\Snappdf\Snappdf;



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
     * @return \Illuminate\Http\Response
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
}
