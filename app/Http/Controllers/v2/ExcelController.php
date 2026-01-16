<?php

namespace App\Http\Controllers\v2;

use App\Exports\v2\A3ERPOrderSalesDeliveryNoteExport;
use App\Exports\v2\A3ERPOrdersSalesDeliveryNotesExport;
use App\Exports\v2\A3ERP2OrderSalesDeliveryNoteExport;
use App\Exports\v2\A3ERP2OrdersSalesDeliveryNotesExport;
use App\Exports\v2\FacilcomOrderSalesDeliveryNoteExport;
use App\Exports\v2\FacilcomOrdersSalesDeliveryNotesExport;
use App\Exports\v2\OrderBoxListExport;
use App\Exports\v2\ActiveOrderPlannedProductsExport;
use App\Exports\v2\BoxesReportExport;
use App\Exports\v2\RawMaterialReceptionFacilcomExport;
use App\Exports\v2\RawMaterialReceptionA3erpExport;
use App\Exports\v2\CeboDispatchFacilcomExport;
use App\Exports\v2\CeboDispatchA3erpExport;
use App\Exports\v2\CeboDispatchA3erp2Export;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\v2\OrdersExport;
use App\Exports\v2\ProductLotDetailsExport;
use App\Models\Order;

class ExcelController extends Controller
{
    /**
     * Aplicar límites de memoria y tiempo para exportaciones
     * 
     * @param string $exportType Tipo de exportación (para configuraciones específicas)
     * @return void
     */
    private function applyExportLimits(string $exportType = 'standard'): void
    {
        // Obtener configuración de límites
        $config = config("exports.types.{$exportType}", 'standard');
        $limits = config("exports.limits.{$config}");

        if ($limits) {
            ini_set('memory_limit', $limits['memory_limit']);
            ini_set('max_execution_time', (string) $limits['max_execution_time']);
        }
    }

    /**
     * Generar exportación en función del tipo de archivo y entidad
     */
    private function generateExport($exportClass, $fileName)
    {
        return Excel::download(new $exportClass, "{$fileName}.xlsx");
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

    public function exportOrders(Request $request)
    {
        $this->applyExportLimits('standard');
        return $this->generateExport(OrdersExport::class, 'orders_report');
    }


    public function exportProductLotDetails($orderId)
    {
        $this->applyExportLimits('standard');
        $order = Order::findOrFail($orderId);
        return Excel::download(new ProductLotDetailsExport($order), "product_lot_details_{$order->formattedId}.xlsx");
    }

    public function exportBoxList($orderId)
    {
        $this->applyExportLimits('standard');
        $order = Order::findOrFail($orderId);
        return Excel::download(new OrderBoxListExport($order), "box_list_{$order->formattedId}.xlsx");
    }


    public function exportA3ERPOrderSalesDeliveryNote($orderId)
    {
        $this->applyExportLimits('standard');
        $order = Order::findOrFail($orderId);
        return Excel::download(new A3ERPOrderSalesDeliveryNoteExport($order), "albaran_venta_{$order->formattedId}.xls");
    }

    public function exportA3ERPOrderSalesDeliveryNoteWithFilters(Request $request)
    {
        $this->applyExportLimits('standard');

        $query = Order::query();

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

        $query->orderBy('load_date', 'desc');

        $orders = $query->get(); // exporta todos los resultados filtrados

        return Excel::download(
            new A3ERPOrdersSalesDeliveryNotesExport($orders),
            'albaran_venta_filtrado.xls',
            \Maatwebsite\Excel\Excel::XLS
        );
    }

    public function exportFacilcomOrderSalesDeliveryNoteWithFilters(Request $request)
    {
        $this->applyExportLimits('standard');

        $query = Order::query();

        if ($request->has('active')) {
            if ($request->active == 'true') {
                $query->where(function ($q) {
                    $q->where('status', 'pending')->orWhereDate('load_date', '>=', now());
                });
            } else {
                $query->where('status', 'finished')->whereDate('load_date', '<', now());
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

        $query->orderBy('load_date', 'desc');
        $orders = $query->get();

        return Excel::download(
            new FacilcomOrdersSalesDeliveryNotesExport($orders),
            'albaran_facilcom.xls',
            \Maatwebsite\Excel\Excel::XLS
        );
    }

    public function exportFacilcomSingleOrder($orderId)
    {
        $this->applyExportLimits('standard');
        $order = Order::findOrFail($orderId);

        return Excel::download(
            new FacilcomOrderSalesDeliveryNoteExport($order),
            "albaran_facilcom_{$order->formattedId}.xls",
            \Maatwebsite\Excel\Excel::XLS
        );
    }

    /* A3ERP2 Order Sales Delivery Note Export - Formato A3 con códigos Facilcom, solo clientes con facilcom_code */
    public function exportA3ERP2OrderSalesDeliveryNote($orderId)
    {
        $this->applyExportLimits('standard');
        $order = Order::findOrFail($orderId);
        
        return Excel::download(
            new A3ERP2OrderSalesDeliveryNoteExport($order),
            "albaran_venta_a3erp2_{$order->formattedId}.xls",
            \Maatwebsite\Excel\Excel::XLS
        );
    }

    /* A3ERP2 Orders Sales Delivery Notes Export - Formato A3 con códigos Facilcom, solo clientes con facilcom_code */
    public function exportA3ERP2OrderSalesDeliveryNoteWithFilters(Request $request)
    {
        $this->applyExportLimits('standard');

        $query = Order::query();

        // IMPORTANTE: Solo exportar pedidos de clientes con código Facilcom
        $query->whereHas('customer', function ($q) {
            $q->whereNotNull('facilcom_code')
              ->where('facilcom_code', '!=', '');
        });

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

        $query->orderBy('load_date', 'desc');

        $orders = $query->get();

        return Excel::download(
            new A3ERP2OrdersSalesDeliveryNotesExport($orders),
            'albaran_venta_a3erp2_filtrado.xls',
            \Maatwebsite\Excel\Excel::XLS
        );
    }

    public function exportActiveOrderPlannedProducts()
    {
        $this->applyExportLimits('standard');

        return Excel::download(
            new ActiveOrderPlannedProductsExport(),
            'productos_previstos_pedidos_activos.xlsx'
        );
    }

    /* Boxes report v2 */
    public function exportBoxesReport(Request $request)
    {
        $this->applyExportLimits('boxes_report');

        // Verificar si se solicita un límite para testing
        $limit = $request->input('limit');
        
        // Probar Excel::download() directamente ahora que las rutas están correctas
        return Excel::download(
            new BoxesReportExport($request, $limit),
            'reporte_cajas.xlsx'
        );
    }

    /* Raw Material Reception Facilcom Export v2 */
    public function exportRawMaterialReceptionFacilcom(Request $request)
    {
        try {
            $this->applyExportLimits('raw_material_reception_facilcom');

            // Verificar si se solicita un límite para testing
            $limit = $request->input('limit');
            
            return Excel::download(
                new RawMaterialReceptionFacilcomExport($request, $limit),
                'recepciones_materia_prima_facilcom.xls',
                \Maatwebsite\Excel\Excel::XLS
            );
            
        } catch (\Exception $e) {
            \Log::error('Error en exportación Facilcom v2: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Error durante la exportación: ' . $e->getMessage()
            ], 500);
        }
    }

    /* Cebo Dispatch Facilcom Export v2 */
    public function exportCeboDispatchFacilcom(Request $request)
    {
        try {
            $this->applyExportLimits('cebo_dispatch_facilcom');

            // Verificar si se solicita un límite para testing
            $limit = $request->input('limit');
            
            return Excel::download(
                new CeboDispatchFacilcomExport($request, $limit),
                'despachos_cebo_facilcom.xlsx',
                \Maatwebsite\Excel\Excel::XLSX
            );
            
        } catch (\Exception $e) {
            \Log::error('Error en exportación Cebo Dispatch Facilcom v2: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Error durante la exportación: ' . $e->getMessage()
            ], 500);
        }
    }

    /* Cebo Dispatch A3erp Export v2 */
    public function exportCeboDispatchA3erp(Request $request)
    {
        try {
            $this->applyExportLimits('cebo_dispatch_a3erp');

            // Verificar si se solicita un límite para testing
            $limit = $request->input('limit');
            
            return Excel::download(
                new CeboDispatchA3erpExport($request, $limit),
                'despachos_cebo_a3erp.xls',
                \Maatwebsite\Excel\Excel::XLS
            );
            
        } catch (\Exception $e) {
            \Log::error('Error en exportación Cebo Dispatch A3erp v2: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Error durante la exportación: ' . $e->getMessage()
            ], 500);
        }
    }

    /* Cebo Dispatch A3erp2 Export v2 - Formato A3 con códigos Facilcom, solo tipo facilcom */
    public function exportCeboDispatchA3erp2(Request $request)
    {
        try {
            $this->applyExportLimits('cebo_dispatch_a3erp2');

            // Verificar si se solicita un límite para testing
            $limit = $request->input('limit');
            
            return Excel::download(
                new CeboDispatchA3erp2Export($request, $limit),
                'despachos_cebo_a3erp2.xls',
                \Maatwebsite\Excel\Excel::XLS
            );
            
        } catch (\Exception $e) {
            \Log::error('Error en exportación Cebo Dispatch A3erp2 v2: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Error durante la exportación: ' . $e->getMessage()
            ], 500);
        }
    }

    /* Raw Material Reception A3erp Export v2 */
    public function exportRawMaterialReceptionA3erp(Request $request)
    {
        try {
            $this->applyExportLimits('raw_material_reception_a3erp');

            // Verificar si se solicita un límite para testing
            $limit = $request->input('limit');
            
            return Excel::download(
                new RawMaterialReceptionA3erpExport($request, $limit),
                'recepciones_materia_prima_a3erp.xls',
                \Maatwebsite\Excel\Excel::XLS
            );
            
        } catch (\Exception $e) {
            \Log::error('Error en exportación Raw Material Reception A3erp v2: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Error durante la exportación: ' . $e->getMessage()
            ], 500);
        }
    }

}
