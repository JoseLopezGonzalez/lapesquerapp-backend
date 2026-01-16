<?php

namespace App\Http\Controllers\v2;

use App\Exports\v2\OrderExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\IOFactory;

class OrdersReportController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    /*return response()->json(['message' => 'Hola Mundo'], 200);*/
    /* return PalletResource::collection(Pallet::all()); */

    /*  public function index()
    {
        return PalletResource::collection(Pallet::paginate(10));

    } */



    public function exportToExcel(Request $request)
    {
        try {
            // Aumentar el límite de memoria y tiempo de ejecución solo para esta operación
            $limits = config('exports.operations.reports');
            if ($limits) {
                ini_set('memory_limit', $limits['memory_limit']);
                ini_set('max_execution_time', (string) $limits['max_execution_time']);
            }

            // Exportar en formato .xls (Excel 97-2003)
            return Excel::download(new OrderExport($request), 'orders_report.xls', \Maatwebsite\Excel\Excel::XLS);
        } catch (\Exception $e) {
            // Manejo de la excepción y retorno de un mensaje de error adecuado
            return response()->json(['error' => 'Error durante la exportación del archivo: ' . $e->getMessage()], 500);
        }
    }


    /* A3ERPOrderSalesDeliveryNoteExport */
     public function exportToExcelA3ERP(Request $request)
     {
         try {
             // Aumentar el límite de memoria y tiempo de ejecución solo para esta operación
             $limits = config('exports.operations.reports');
             if ($limits) {
                 ini_set('memory_limit', $limits['memory_limit']);
                 ini_set('max_execution_time', (string) $limits['max_execution_time']);
             }

             // Exportar en formato .xls (Excel 97-2003)
             return Excel::download(new OrderExport($request), 'orders_report_a3erp.xls', \Maatwebsite\Excel\Excel::XLS);
         } catch (\Exception $e) {
             // Manejo de la excepción y retorno de un mensaje de error adecuado
             return response()->json(['error' => 'Error durante la exportación del archivo: ' . $e->getMessage()], 500);
         }
     }

    
    





}
