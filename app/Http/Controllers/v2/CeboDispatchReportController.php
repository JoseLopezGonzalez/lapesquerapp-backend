<?php

namespace App\Http\Controllers\v2;

use App\Exports\v2\CeboDispatchA3erpExport;
use App\Exports\v2\CeboDispatchFacilcomExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class CeboDispatchReportController extends Controller
{
    /**
     * Exportar reporte de despachos de cebo a Facilcom en formato Excel
     */
    public function exportToFacilcomExcel(Request $request)
    {
        try {
            // Aumentar el límite de memoria y tiempo de ejecución solo para esta operación
            ini_set('memory_limit', '1024M');
            ini_set('max_execution_time', 300);

            // Exportar en formato .xlsx (Excel moderno)
            return Excel::download(
                new CeboDispatchFacilcomExport($request), 
                'cebo_dispatch_report_facilcom_v2.xlsx', 
                \Maatwebsite\Excel\Excel::XLSX
            );
        } catch (\Exception $e) {
            // Manejo de la excepción y retorno de un mensaje de error adecuado
            return response()->json([
                'error' => 'Error durante la exportación del archivo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exportar reporte de despachos de cebo a A3erp en formato Excel
     */
    public function exportToA3erpExcel(Request $request)
    {
        try {
            // Aumentar el límite de memoria y tiempo de ejecución solo para esta operación
            ini_set('memory_limit', '1024M');
            ini_set('max_execution_time', 300);

            // Exportar en formato .xlsx (Excel moderno)
            return Excel::download(
                new CeboDispatchA3erpExport($request), 
                'cebo_dispatch_report_a3erp_v2.xlsx', 
                \Maatwebsite\Excel\Excel::XLSX
            );
        } catch (\Exception $e) {
            // Manejo de la excepción y retorno de un mensaje de error adecuado
            return response()->json([
                'error' => 'Error durante la exportación del archivo: ' . $e->getMessage()
            ], 500);
        }
    }
} 