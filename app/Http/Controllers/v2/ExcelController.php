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
use App\Http\Requests\v2\OrderFilteredExportRequest;
use App\Models\Order;
use App\Services\v2\OrderExportFilterService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\v2\OrderExport;
use App\Exports\v2\ProductLotDetailsExport;

class ExcelController extends Controller
{
    public function __construct(
        protected OrderExportFilterService $filterService
    ) {}

    private function applyExportLimits(string $exportType = 'standard'): void
    {
        $config = config("exports.types.{$exportType}", 'standard');
        $limits = config("exports.limits.{$config}");

        if ($limits) {
            ini_set('memory_limit', $limits['memory_limit']);
            ini_set('max_execution_time', (string) $limits['max_execution_time']);
        }
    }

    public function exportOrders(Request $request)
    {
        $this->applyExportLimits('standard');
        $this->authorize('viewAny', Order::class);

        return Excel::download(new OrderExport($request), 'orders_report.xlsx');
    }

    public function exportProductLotDetails(int|string $orderId)
    {
        $this->applyExportLimits('standard');
        $order = Order::findOrFail($orderId);
        $this->authorize('view', $order);

        return Excel::download(new ProductLotDetailsExport($order), "product_lot_details_{$order->formattedId}.xlsx");
    }

    public function exportBoxList(int|string $orderId)
    {
        $this->applyExportLimits('standard');
        $order = Order::findOrFail($orderId);
        $this->authorize('view', $order);

        return Excel::download(new OrderBoxListExport($order), "box_list_{$order->formattedId}.xlsx");
    }

    public function exportA3ERPOrderSalesDeliveryNote(int|string $orderId)
    {
        $this->applyExportLimits('standard');
        $order = Order::findOrFail($orderId);
        $this->authorize('view', $order);

        return Excel::download(
            new A3ERPOrderSalesDeliveryNoteExport($order),
            "albaran_venta_{$order->formattedId}.xls",
            \Maatwebsite\Excel\Excel::XLS
        );
    }

    public function exportA3ERPOrderSalesDeliveryNoteWithFilters(OrderFilteredExportRequest $request)
    {
        $this->applyExportLimits('standard');

        $orders = $this->filterService->getFilteredOrders($request);

        return Excel::download(
            new A3ERPOrdersSalesDeliveryNotesExport($orders),
            'albaran_venta_filtrado.xls',
            \Maatwebsite\Excel\Excel::XLS
        );
    }

    public function exportFacilcomOrderSalesDeliveryNoteWithFilters(OrderFilteredExportRequest $request)
    {
        $this->applyExportLimits('standard');

        $orders = $this->filterService->getFilteredOrders($request);

        return Excel::download(
            new FacilcomOrdersSalesDeliveryNotesExport($orders),
            'albaran_facilcom.xls',
            \Maatwebsite\Excel\Excel::XLS
        );
    }

    public function exportFacilcomSingleOrder(int|string $orderId)
    {
        $this->applyExportLimits('standard');
        $order = Order::findOrFail($orderId);
        $this->authorize('view', $order);

        return Excel::download(
            new FacilcomOrderSalesDeliveryNoteExport($order),
            "albaran_facilcom_{$order->formattedId}.xls",
            \Maatwebsite\Excel\Excel::XLS
        );
    }

    public function exportA3ERP2OrderSalesDeliveryNote(int|string $orderId)
    {
        $this->applyExportLimits('standard');
        $order = Order::findOrFail($orderId);
        $this->authorize('view', $order);

        return Excel::download(
            new A3ERP2OrderSalesDeliveryNoteExport($order),
            "albaran_venta_a3erp2_{$order->formattedId}.xls",
            \Maatwebsite\Excel\Excel::XLS
        );
    }

    public function exportA3ERP2OrderSalesDeliveryNoteWithFilters(OrderFilteredExportRequest $request)
    {
        $this->applyExportLimits('standard');

        $orders = $this->filterService->getFilteredOrders($request);

        return Excel::download(
            new A3ERP2OrdersSalesDeliveryNotesExport($orders),
            'albaran_venta_a3erp2_filtrado.xls',
            \Maatwebsite\Excel\Excel::XLS
        );
    }

    public function exportActiveOrderPlannedProducts()
    {
        $this->applyExportLimits('standard');
        $this->authorize('viewAny', Order::class);

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
