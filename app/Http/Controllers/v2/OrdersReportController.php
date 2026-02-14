<?php

namespace App\Http\Controllers\v2;

use App\Exports\v2\OrderExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\v2\OrdersReportRequest;
use Maatwebsite\Excel\Facades\Excel;

class OrdersReportController extends Controller
{
    public function exportToExcel(OrdersReportRequest $request)
    {
        try {
            // Aumentar el límite de memoria y tiempo de ejecución solo para esta operación
            $limits = config('exports.operations.reports');
            if ($limits) {
                ini_set('memory_limit', $limits['memory_limit']);
                ini_set('max_execution_time', (string) $limits['max_execution_time']);
            }

            return Excel::download(new OrderExport($request), 'orders_report.xls', \Maatwebsite\Excel\Excel::XLS);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error durante la exportación del archivo: ' . $e->getMessage()], 500);
        }
    }


    public function exportToExcelA3ERP(OrdersReportRequest $request)
    {
        try {
            $limits = config('exports.operations.reports');
            if ($limits) {
                ini_set('memory_limit', $limits['memory_limit']);
                ini_set('max_execution_time', (string) $limits['max_execution_time']);
            }

            return Excel::download(new OrderExport($request), 'orders_report_a3erp.xls', \Maatwebsite\Excel\Excel::XLS);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error durante la exportación del archivo: ' . $e->getMessage()], 500);
        }
    }

    
    





}
