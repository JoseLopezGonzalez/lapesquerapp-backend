<?php

namespace App\Http\Controllers\v2;

use App\Exports\v2\A3ERPOrderSalesDeliveryNoteExport;
use App\Exports\v2\A3ERPOrdersSalesDeliveryNotesExport;
use App\Exports\v2\FacilcomOrderSalesDeliveryNoteExport;
use App\Exports\v2\FacilcomOrdersSalesDeliveryNotesExport;
use App\Exports\v2\OrderBoxListExport;
use App\Exports\v2\ActiveOrderPlannedProductsExport;
use App\Exports\v2\BoxesReportExport;
use App\Exports\v2\RawMaterialReceptionFacilcomExport;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\v2\OrdersExport;
use App\Exports\v2\ProductLotDetailsExport;
use App\Models\Order;

class ExcelController extends Controller
{
    /**
     * Generar exportación en función del tipo de archivo y entidad
     */
    private function generateExport($exportClass, $fileName)
    {
        return Excel::download(new $exportClass, "{$fileName}.xlsx");
    }

    public function exportOrders(Request $request)
    {
        ini_set('memory_limit', '1024M');
        return $this->generateExport(OrdersExport::class, 'orders_report');
    }


    public function exportProductLotDetails($orderId)
    {
        ini_set('memory_limit', '1024M');
        $order = Order::findOrFail($orderId);
        return Excel::download(new ProductLotDetailsExport($order), "product_lot_details_{$order->formattedId}.xlsx");
    }

    public function exportBoxList($orderId)
    {
        ini_set('memory_limit', '1024M');
        $order = Order::findOrFail($orderId);
        return Excel::download(new OrderBoxListExport($order), "box_list_{$order->formattedId}.xlsx");
    }


    public function exportA3ERPOrderSalesDeliveryNote($orderId)
    {
        ini_set('memory_limit', '1024M');
        $order = Order::findOrFail($orderId);
        return Excel::download(new A3ERPOrderSalesDeliveryNoteExport($order), "albaran_venta_{$order->formattedId}.xls");
    }

    public function exportA3ERPOrderSalesDeliveryNoteWithFilters(Request $request)
    {
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', 300);

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
            $query->whereIn('customer_id', $request->customers);
        }

        if ($request->has('id')) {
            $query->where('id', 'like', '%' . $request->id . '%');
        }

        if ($request->has('ids')) {
            $query->whereIn('id', $request->ids);
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
            $query->whereIn('transport_id', $request->transports);
        }

        if ($request->has('salespeople')) {
            $query->whereIn('salesperson_id', $request->salespeople);
        }

        if ($request->has('palletsState')) {
            if ($request->palletsState == 'stored') {
                $query->whereHas('pallets', fn($q) => $q->where('state_id', 2));
            } elseif ($request->palletsState == 'shipping') {
                $query->whereHas('pallets', fn($q) => $q->where('state_id', 3));
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
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', 300);

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
            $query->whereIn('customer_id', $request->customers);
        }

        if ($request->has('id')) {
            $query->where('id', 'like', '%' . $request->id . '%');
        }

        if ($request->has('ids')) {
            $query->whereIn('id', $request->ids);
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
            $query->whereIn('transport_id', $request->transports);
        }

        if ($request->has('salespeople')) {
            $query->whereIn('salesperson_id', $request->salespeople);
        }

        if ($request->has('palletsState')) {
            if ($request->palletsState == 'stored') {
                $query->whereHas('pallets', fn($q) => $q->where('state_id', 2));
            } elseif ($request->palletsState == 'shipping') {
                $query->whereHas('pallets', fn($q) => $q->where('state_id', 3));
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
        ini_set('memory_limit', '1024M');
        $order = Order::findOrFail($orderId);

        return Excel::download(
            new FacilcomOrderSalesDeliveryNoteExport($order),
            "albaran_facilcom_{$order->formattedId}.xls",
            \Maatwebsite\Excel\Excel::XLS
        );
    }

    public function exportActiveOrderPlannedProducts()
    {
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', 300);

        return Excel::download(
            new ActiveOrderPlannedProductsExport(),
            'productos_previstos_pedidos_activos.xlsx'
        );
    }

    /* Boxes report v2 */
    public function exportBoxesReport(Request $request)
    {
        ini_set('memory_limit', '2048M');
        ini_set('max_execution_time', 600);

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
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', 300);

        return Excel::download(
            new RawMaterialReceptionFacilcomExport($request),
            'recepciones_materia_prima_facilcom.xls',
            \Maatwebsite\Excel\Excel::XLS
        );
    }

}
