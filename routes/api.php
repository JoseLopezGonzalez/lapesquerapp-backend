<?php

use App\Http\Controllers\Public\TenantController;
use App\Http\Controllers\v2\OrderDocumentController;
use App\Http\Controllers\v1\AuthController;
use App\Http\Controllers\v1\AutoSalesController;
use App\Http\Controllers\v1\OrderDocumentMailerController;
use App\Http\Controllers\v1\BoxesReportController;
use App\Http\Controllers\v1\CaptureZoneController;
use App\Http\Controllers\v1\CeboController;
use App\Http\Controllers\v1\CeboDispatchController;
use App\Http\Controllers\v1\CeboDispatchReportController;
use App\Http\Controllers\v1\CustomerController;
use App\Http\Controllers\v1\IncotermController;
use App\Http\Controllers\v1\OrderController;
use App\Http\Controllers\v1\PalletController;
use App\Http\Controllers\v1\PaymentTermController;
use App\Http\Controllers\v1\PDFController;
use App\Http\Controllers\v1\ProcessController;
use App\Http\Controllers\v1\ProductController;
use App\Http\Controllers\v1\ProductionController;
use App\Http\Controllers\v1\RawMaterialController;
use App\Http\Controllers\v1\RawMaterialReceptionController;
use App\Http\Controllers\v1\RawMaterialReceptionsReportController;
use App\Http\Controllers\v1\RawMaterialReceptionsStatsController;
use App\Http\Controllers\v1\SalespersonController;
use App\Http\Controllers\v1\SpeciesController;
use App\Http\Controllers\v1\ProcessNodeController;
use App\Http\Controllers\v1\FinalNodeController;
use App\Http\Controllers\v2\PdfExtractionController;
use App\Http\Controllers\v2\SettingController;
use App\Http\Controllers\v2\TaxController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\v1\StoreController;
use App\Http\Controllers\v1\StoredPalletController;
use App\Http\Controllers\v1\StoresStatsController;
use App\Http\Controllers\v1\SupplierController;
use App\Http\Controllers\v1\TransportController;
use App\Http\Controllers\v2\ActivityLogController;
use App\Http\Controllers\v2\AuthController as V2AuthController;
use App\Http\Controllers\v2\AzureDocumentAIController;
use App\Http\Controllers\v2\BoxesController;
use App\Http\Controllers\v2\CaptureZoneController as V2CaptureZoneController;
use App\Http\Controllers\v2\CeboDispatchController as V2CeboDispatchController;
use App\Http\Controllers\v2\CeboDispatchStatisticsController;
use App\Http\Controllers\v2\CountryController;
use App\Http\Controllers\v2\CustomerController as V2CustomerController;
use App\Http\Controllers\v2\FishingGearController;
use App\Http\Controllers\v2\GoogleDocumentAIController;
use App\Http\Controllers\v2\IncidentController;
use App\Http\Controllers\v2\IncotermController as V2IncotermController;
use App\Http\Controllers\v2\LabelController;
use App\Http\Resources\v1\CustomerResource;
use App\Models\PaymentTerm;
use Illuminate\Support\Facades\App;

/* API V2 */
use App\Http\Controllers\v2\OrderController as V2OrderController;
use App\Http\Controllers\v2\OrderPlannedProductDetailController;
use App\Http\Controllers\v2\OrdersReportController;
use App\Http\Controllers\v2\OrderStatisticsController;
use App\Http\Controllers\v2\PalletController as V2PalletController;
use App\Http\Controllers\v2\PaymentTermController as V2PaymentTermController;
use App\Http\Controllers\v2\ProductController as V2ProductController;
use App\Http\Controllers\v2\ProductCategoryController;
use App\Http\Controllers\v2\ProductFamilyController;
use App\Http\Controllers\v2\RawMaterialReceptionController as V2RawMaterialReceptionController;
use App\Http\Controllers\v2\RawMaterialReceptionStatisticsController;
use App\Http\Controllers\v2\RoleController;
use App\Http\Controllers\v2\SalespersonController as V2SalespersonController;
use App\Http\Controllers\v2\SessionController;
use App\Http\Controllers\v2\SpeciesController as V2SpeciesController;
use App\Http\Controllers\v2\StockStatisticsController;
use App\Http\Controllers\v2\StoreController as V2StoreController;
use App\Http\Controllers\v2\SupplierController as V2SupplierController;
use App\Http\Controllers\v2\TransportController as V2TransportController;
use App\Http\Controllers\v2\UserController;
use App\Http\Controllers\v2\ProcessController as V2ProcessController;
use App\Http\Controllers\v2\ProductionController as V2ProductionController;
use App\Http\Controllers\v2\ProductionRecordController;
use App\Http\Controllers\v2\ProductionInputController;
use App\Http\Controllers\v2\ProductionOutputController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

/* Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
}); */


/* Route::middleware(['cors'])->group(function () {
    Route::apiResource('v1/stores/pallets', StoredPalletController::class);
    Route::apiResource('v1/stores', StoreController::class)->only(['show' , 'index']);
    Route::apiResource('v1/articles/products', ProductController::class)->only(['show' , 'index']);
}); */

Route::post('v1/register', [AuthController::class, 'register']);
Route::post('v1/login', [AuthController::class, 'login']);
Route::post('v1/logout', [AuthController::class, 'logout']);
Route::get('v1/me', [AuthController::class, 'me'])->middleware('auth:api');


//Route::group(['middleware' => ['auth:api']], function () {

Route::apiResource('v1/stores/pallets', StoredPalletController::class)
    ->names([
        'index' => 'stores.pallets.index',
        'create' => 'stores.pallets.create',
        'store' => 'stores.pallets.store',
        'show' => 'stores.pallets.show',
        'edit' => 'stores.pallets.edit',
        'update' => 'stores.pallets.update',
        'destroy' => 'stores.pallets.destroy',
    ]);
Route::apiResource('v1/pallets', PalletController::class);
Route::apiResource('v1/stores', StoreController::class)->only(['show', 'index']);
Route::apiResource('v1/articles/products', ProductController::class)->only(['show', 'index']);
Route::apiResource('v1/customers', CustomerController::class);
Route::apiResource('v1/orders', OrderController::class);
Route::apiResource('v1/transports', TransportController::class);
Route::apiResource('v1/salespeople', SalespersonController::class);
Route::apiResource('v1/payment_terms', PaymentTermController::class);
Route::apiResource('v1/suppliers', SupplierController::class);
Route::apiResource('v1/raw-material-receptions', RawMaterialReceptionController::class);
/* updateDeclaredData */
Route::post('/v1/raw-material-receptions/update-declared-data', [RawMaterialReceptionController::class, 'updateDeclaredData']);

Route::apiResource('v1/cebo-dispatches', CeboDispatchController::class);
Route::apiResource('v1/species', SpeciesController::class);
/* CaptureZones */
Route::apiResource('v1/capture_zones', CaptureZoneController::class);
Route::apiResource('v1/raw-materials', RawMaterialController::class);
Route::apiResource('v1/cebos', CeboController::class);
Route::get('v1/productions/get-production-id-by-lot', [ProductionController::class, 'getProductionIdByLot'])->name('productions.getProductionIdByLot');
Route::apiResource('v1/productions', ProductionController::class);


/* getProductionIdByLot */
Route::apiResource('v1/processes', ProcessController::class);







/* Incorterm */
Route::apiResource('v1/incoterms', IncotermController::class);
Route::get('v1/boxes_report', [BoxesReportController::class, 'exportToExcel'])->name('export.boxes');
/* RawMaterialReceptionsReportController */
Route::get('v1/raw_material_receptions_report', [RawMaterialReceptionsReportController::class, 'exportToExcel'])->name('export.raw_material_receptions');
// Ruta personalizada para enviar documentación de un pedido (NO CRUD)
/* v1/cebo_dispatches_report */
Route::get('v1/cebo_dispatches_report/facilcom', [CeboDispatchReportController::class, 'exportToFacilcomExcel'])->name('export.cebo_dispatches_facilcom');
/* v1/cebo_dispatches_report/a3erp */
Route::get('v1/cebo_dispatches_report/a3erp', [CeboDispatchReportController::class, 'exportToA3erpExcel'])->name('export.cebo_dispatches_a3erp');


Route::post('v1/send_order_documentation/{orderId}', [OrderDocumentMailerController::class, 'sendDocumentation'])->name('send_order_documentation');
/* Send order documentation to Transport  */
Route::post('v1/send_order_documentation_transport/{orderId}', [OrderDocumentMailerController::class, 'sendDocumentationTransport'])->name('send_order_documentation_transport');


Route::get('v1/orders/{orderId}/delivery-note', [PDFController::class, 'generateDeliveryNote'])->name('generate_delivery_note');
Route::get('v1/orders/{orderId}/restricted-delivery-note', [PDFController::class, 'generateRestrictedDeliveryNote'])->name('generate_restricted_delivery_note');
Route::get('v1/orders/{orderId}/order-signs', [PDFController::class, 'generateOrderSigns'])->name('generate_order_signs');
Route::get('v1/orders/{orderId}/order_CMR', [PDFController::class, 'generateOrderCMR'])->name('generate_order_CMR');

/* La Pesca del Meridión */
Route::get('v1/orders/{orderId}/order_CMR_pesca', [PDFController::class, 'generateOrderCMRPesca'])->name('generate_order_CMR_Pesca');
Route::get('v1/orders/{orderId}/delivery-note-pesca', [PDFController::class, 'generateDeliveryNotePesca'])->name('generate_delivery_note_pesca');
Route::get('v1/orders/{orderId}/restricted-delivery-note-pesca', [PDFController::class, 'generateRestrictedDeliveryNotePesca'])->name('generate_restricted_delivery_note_pesca');
Route::get('v1/orders/{orderId}/order-signs-pesca', [PDFController::class, 'generateOrderSignsPesca'])->name('generate_order_signs_pesca');

/* d */
Route::get('v1/rawMaterialReceptions/document', [PDFController::class, 'generateRawMaterialReceptionsDocument'])->name('generate_raw_material_receptions_document');

/* No funciona */
/* Route::get('v1/monthly-stats', [RawMaterialReceptionsStatsController::class, 'getMonthlyStats'])->name('raw_material_receptions.monthly_stats'); */


/* Process Node  */
Route::get('v1/process-nodes-decrease', [ProcessNodeController::class, 'getProcessNodesDecrease']);
/* getProcessNodesDecreaseStats */
Route::get('v1/process-nodes-decrease-stats', [ProcessNodeController::class, 'getProcessNodesDecreaseStats']);

/* Final node */
Route::get('v1/final-nodes-profit', [FinalNodeController::class, 'getFinalNodesProfit']);
/* getFinalNodesCostPerKgByDay */
Route::get('v1/final-nodes-cost-per-kg-by-day', [FinalNodeController::class, 'getFinalNodesCostPerKgByDay']);
/* getFinalNodesDailyProfit */
Route::get('v1/final-nodes-daily-profit', [FinalNodeController::class, 'getFinalNodesDailyProfit']);


/* No funciona */
Route::get('v1/raw-material-receptions-monthly-stats', [RawMaterialReceptionsStatsController::class, 'getMonthlyStats'])->name('raw_material_receptions.monthly_stats');
Route::get('v1/raw-material-receptions-annual-stats', [RawMaterialReceptionsStatsController::class, 'getAnnualStats'])->name('raw_material_receptions.annual_stats');
Route::get('v1/raw-material-receptions-daily-by-products-stats', [RawMaterialReceptionsStatsController::class, 'getDailyByProductsStats'])->name('raw_material_receptions.daily_by_products_stats');
/* totalInventoryBySpecies */
Route::get('v1/total-inventory-by-species', [StoresStatsController::class, 'totalInventoryBySpecies'])->name('total_inventory_by_species');
/* totalInventoryByProducts */
Route::get('v1/total-inventory-by-products', [StoresStatsController::class, 'totalInventoryByProducts'])->name('total_inventory_by_products');

Route::get('v1/ceboDispatches/document', [PDFController::class, 'generateCeboDispatchesDocument'])->name('generate_cebo_document');


Route::get('v1/process-options', [ProcessController::class, 'options']);

Route::get('/test-cors', function (Request $request) {
    return response()->json(['message' => 'CORS funciona correctamente!'], 200)
        ->header('Access-Control-Allow-Origin', $request->header('Origin'))
        ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, PUT, DELETE')
        ->header('Access-Control-Allow-Headers', 'Origin, Content-Type, Authorization')
        ->header('Access-Control-Allow-Credentials', 'true');
});

/* autoSalesCustomers */
Route::get('v1/auto-sales-customers', [CustomerController::class, 'autoSalesCustomers']);

/* autoSalesController store*/
Route::apiResource('v1/auto-sales', AutoSalesController::class);

/* metodo autoSalesCustomer de customerController */
Route::post('v1/insert-auto-sales-customers', [CustomerController::class, 'insertAutoSalesCustomers']);












/* Api V2 */
/* Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('v2/login', [V2AuthController::class, 'login'])->name('login');
    Route::post('v2/logout', [V2AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('v2/me', [V2AuthController::class, 'me'])->middleware('auth:sanctum');
    Route::apiResource('v2/orders', V2OrderController::class);
    Route::apiResource('v2/raw-material-receptions', V2RawMaterialReceptionController::class);
    Route::get('v2/orders_report', [OrdersReportController::class, 'exportToExcel'])->name('export.orders');
}); */

/* IMPORTANTISIMO */
Route::get('v2/public/tenant/{subdomain}', [TenantController::class, 'showBySubdomain']);

/* Comprobar el tenant ya que esta aplicado de manera global */
Route::group(['prefix' => 'v2', 'as' => 'v2.', 'middleware' => ['tenant']], function () {
    // Rutas públicas (sin autenticación)
    Route::post('login', [V2AuthController::class, 'login'])->name('v2.login');
    Route::post('logout', [V2AuthController::class, 'logout'])->middleware('auth:sanctum')->name('v2.logout');
    Route::get('me', [V2AuthController::class, 'me'])->middleware('auth:sanctum')->name('v2.me');
    Route::get('/customers/op', [V2CustomerController::class, 'options']);


    // Rutas protegidas por Sanctum
    Route::middleware(['auth:sanctum'])->group(function () {
        // Rutas para Superusuario (Técnico)
        Route::middleware(['role:superuser'])->group(function () {
            /* options */
            Route::get('roles/options', [RoleController::class, 'options']);
            Route::get('users/options', [UserController::class, 'options']);

            /* Descargas */
            Route::get('orders_report', [OrdersReportController::class, 'exportToExcel'])->name('export.orders');

            /* Controladores */
            Route::apiResource('sessions', SessionController::class)->only(['index', 'destroy']);
            Route::apiResource('users', UserController::class);
            Route::apiResource('activity-logs', ActivityLogController::class);
            Route::apiResource('roles', RoleController::class);

            /* Pdf extractor */
            /* Route::post('pdf-extractor', [PdfExtractionController::class, 'extract'])->name('pdf.extract'); */
            /* Route::post('document-ai/parse', [GoogleDocumentAIController::class, 'processPdf']); */
            Route::post('document-ai/parse', [AzureDocumentAIController::class, 'processPdf']);
        });

        // Rutas para Gerencia
        Route::middleware(['role:manager'])->group(function () {});

        // Rutas para Administración
        Route::middleware(['role:admin'])->group(function () {
        });

        // Rutas accesibles para múltiples roles
        Route::middleware(['role:superuser,manager,admin,store_operator'])->group(function () {
            /* Options */
            Route::get('settings', [SettingController::class, 'index']);
            Route::put('settings', [SettingController::class, 'update']);

            Route::get('/customers/options', [V2CustomerController::class, 'options']);
            Route::get('/salespeople/options', [V2SalespersonController::class, 'options']);
            Route::get('/transports/options', [V2TransportController::class, 'options']);
            Route::get('/incoterms/options', [V2IncotermController::class, 'options']);
            Route::get('/suppliers/options', [V2SupplierController::class, 'options']);
            Route::get('/species/options', [V2SpeciesController::class, 'options']);
            Route::get('/products/options', [V2ProductController::class, 'options']);
            Route::get('/product-categories/options', [ProductCategoryController::class, 'options']);
            Route::get('/product-families/options', [ProductFamilyController::class, 'options']);
            Route::get('/taxes/options', [TaxController::class, 'options']);
            Route::get('/capture-zones/options', [V2CaptureZoneController::class, 'options']);
            Route::get('/processes/options', [V2ProcessController::class, 'options']);
            Route::get('/pallets/options', [V2PalletController::class, 'options']);
            Route::get('/pallets/stored-options', [V2PalletController::class, 'storedOptions']);
            Route::get('/pallets/shipped-options', [V2PalletController::class, 'shippedOptions']);
            Route::get('/stores/options', [V2StoreController::class, 'options']);
            Route::get('/orders/options', [V2OrderController::class, 'options']);
            Route::post('/pallets/assign-to-position', [V2PalletController::class, 'assignToPosition']);
            Route::post('/pallets/move-to-store', [V2PalletController::class, 'moveToStore']);
            Route::post('pallets/{id}/unassign-position', [V2PalletController::class, 'unassignPosition']);
            Route::post('/pallets/{id}/unlink-order', [V2PalletController::class, 'unlinkOrder']);




            /* Active Order Options */
            Route::get('/active-orders/options', [V2OrderController::class, 'activeOrdersOptions']);
            Route::get('/fishing-gears/options', [FishingGearController::class, 'options']);
            Route::get('/countries/options', [CountryController::class, 'options']);
            Route::get('/payment-terms/options', [V2PaymentTermController::class, 'options']);
            /* totalStockByProducts */
            Route::get('stores/total-stock-by-products', [V2StoreController::class, 'totalStockByProducts']);
            /* stores/total-stock */
            Route::get('statistics/orders/total-net-weight', [OrderStatisticsController::class, 'totalNetWeightStats']);
            Route::get('statistics/orders/total-amount', [OrderStatisticsController::class, 'totalAmountStats']);
            Route::get('statistics/stock/total', [StockStatisticsController::class, 'totalStockStats'])->name('v2.statistics.stock.total');
            /* totalStockBySpeciesStats */
            Route::get('statistics/stock/total-by-species', [StockStatisticsController::class, 'totalStockBySpeciesStats'])->name('v2.statistics.stock.total_by_species');
            /* orderRankingStats */
            Route::get('statistics/orders/ranking', [OrderStatisticsController::class, 'orderRankingStats'])->name('v2.statistics.orders.ranking');



            /* orders/sales-by-salesperson */
            Route::get('orders/sales-by-salesperson', [V2OrderController::class, 'salesBySalesperson']);
            /* salesChartData */
            Route::get('orders/sales-chart-data', [OrderStatisticsController::class, 'salesChartData']);
            /* orders/transport-chart-data */
            Route::get('orders/transport-chart-data', [V2OrderController::class, 'transportChartData']);

            /* bulkUpdateState - Solo para roles administrativos */
            Route::post('pallets/update-state', [V2PalletController::class, 'bulkUpdateState'])->name('pallets.bulk_update_state')->middleware(['role:superuser,manager,admin']);

            /* Controladores Genericos */
            Route::apiResource('orders', V2OrderController::class);
            Route::delete('orders', [V2OrderController::class, 'destroyMultiple']);
            Route::apiResource('order-planned-product-details', OrderPlannedProductDetailController::class);
            
            /* Raw Material Receptions - Export debe ir ANTES del apiResource */
            Route::get('raw-material-receptions/facilcom-xls', [\App\Http\Controllers\v2\ExcelController::class, 'exportRawMaterialReceptionFacilcom'])->name('export_raw_material_receptions_facilcom');
            Route::get('raw-material-receptions/a3erp-xls', [\App\Http\Controllers\v2\ExcelController::class, 'exportRawMaterialReceptionA3erp'])->name('export_raw_material_receptions_a3erp');
            /* receptionChartData */
            Route::get('raw-material-receptions/reception-chart-data', [RawMaterialReceptionStatisticsController::class, 'receptionChartData']);
            Route::apiResource('raw-material-receptions', V2RawMaterialReceptionController::class);
            Route::delete('raw-material-receptions', [V2RawMaterialReceptionController::class, 'destroyMultiple']);
            Route::apiResource('transports', V2TransportController::class);
            Route::delete('transports', [V2TransportController::class, 'destroyMultiple']);

            Route::apiResource('products', V2ProductController::class);
            Route::delete('products', [V2ProductController::class, 'destroyMultiple']);

            Route::apiResource('product-categories', ProductCategoryController::class);
            Route::delete('product-categories', [ProductCategoryController::class, 'destroyMultiple']);
            Route::apiResource('product-families', ProductFamilyController::class);
            Route::delete('product-families', [ProductFamilyController::class, 'destroyMultiple']);

            Route::apiResource('stores', V2StoreController::class);
            Route::delete('stores', [V2StoreController::class, 'deleteMultiple'])->middleware(['role:superuser,manager,admin']); // <-- importante

            Route::apiResource('payment-terms', V2PaymentTermController::class);
            Route::delete('payment-terms', [V2PaymentTermController::class, 'destroyMultiple']);

            Route::apiResource('countries', CountryController::class);
            Route::delete('countries', [CountryController::class, 'destroyMultiple']);

            Route::get('boxes/xlsx', [\App\Http\Controllers\v2\ExcelController::class, 'exportBoxesReport'])->name('export_boxes_report');

            Route::apiResource('boxes', BoxesController::class); /* Algo raro en el nombre */
            Route::delete('boxes', [BoxesController::class, 'destroyMultiple']);
            Route::apiResource('pallets', V2PalletController::class);
            Route::delete('pallets', [V2PalletController::class, 'destroyMultiple'])->middleware(['role:superuser,manager,admin']);
            Route::apiResource('customers', V2CustomerController::class);
            Route::delete('customers', [V2CustomerController::class, 'destroyMultiple']);

            Route::apiResource('suppliers', V2SupplierController::class);
            Route::delete('suppliers', [V2SupplierController::class, 'destroyMultiple']);

            Route::apiResource('capture-zones', V2CaptureZoneController::class);
            Route::delete('capture-zones', [V2CaptureZoneController::class, 'destroyMultiple']);
            Route::apiResource('species', V2SpeciesController::class);
            Route::delete('species', [V2SpeciesController::class, 'destroyMultiple']);

            Route::apiResource('incoterms', V2IncotermController::class);
            Route::delete('incoterms', [V2IncotermController::class, 'destroyMultiple']);

            Route::apiResource('salespeople', V2SalespersonController::class);
            Route::delete('salespeople', [V2SalespersonController::class, 'destroyMultiple']);

            Route::apiResource('fishing-gears', FishingGearController::class);
            Route::delete('fishing-gears', [FishingGearController::class, 'destroyMultiple']);

            /* Cebo Dispatch Exports - DEBE IR ANTES del apiResource */
            Route::get('cebo-dispatches/facilcom-xlsx', [\App\Http\Controllers\v2\ExcelController::class, 'exportCeboDispatchFacilcom'])->name('export_cebo_dispatches_facilcom');
            Route::get('cebo-dispatches/a3erp-xlsx', [\App\Http\Controllers\v2\ExcelController::class, 'exportCeboDispatchA3erp'])->name('export_cebo_dispatches_a3erp');
            /* dispatchChartData */
            Route::get('cebo-dispatches/dispatch-chart-data', [CeboDispatchStatisticsController::class, 'dispatchChartData']);
            Route::apiResource('cebo-dispatches', V2CeboDispatchController::class);
            Route::delete('cebo-dispatches', [V2CeboDispatchController::class, 'destroyMultiple']);
            Route::get('labels/options', [LabelController::class, 'options'])->name('labels.options');
            Route::apiResource('labels', LabelController::class);

            /* Production Module v2 */
            Route::apiResource('productions', V2ProductionController::class);
            Route::get('productions/{id}/diagram', [V2ProductionController::class, 'getDiagram'])->name('productions.getDiagram');
            Route::get('productions/{id}/process-tree', [V2ProductionController::class, 'getProcessTree'])->name('productions.getProcessTree');
            Route::get('productions/{id}/totals', [V2ProductionController::class, 'getTotals'])->name('productions.getTotals');
            Route::get('productions/{id}/reconciliation', [V2ProductionController::class, 'getReconciliation'])->name('productions.getReconciliation');

            Route::apiResource('production-records', ProductionRecordController::class);
            Route::get('production-records/{id}/tree', [ProductionRecordController::class, 'tree'])->name('production-records.tree');
            Route::post('production-records/{id}/finish', [ProductionRecordController::class, 'finish'])->name('production-records.finish');

            Route::apiResource('production-inputs', ProductionInputController::class);
            Route::post('production-inputs/multiple', [ProductionInputController::class, 'storeMultiple'])->name('production-inputs.storeMultiple');

            Route::apiResource('production-outputs', ProductionOutputController::class);

            /* Processes */
            Route::apiResource('processes', V2ProcessController::class);

            /* order incidents */
            Route::get('orders/{orderId}/incident', [IncidentController::class, 'show']);
            Route::post('orders/{orderId}/incident', [IncidentController::class, 'store']);
            Route::put('orders/{orderId}/incident', [IncidentController::class, 'update']);
            Route::delete('orders/{orderId}/incident', [IncidentController::class, 'destroy']);

            /* Update Order status */
            Route::put('orders/{order}/status', [V2OrderController::class, 'updateStatus'])->name('orders.update_status');

            /* Descargas */
            Route::get('orders/{orderId}/pdf/order-sheet', [\App\Http\Controllers\v2\PDFController::class, 'generateOrderSheet'])->name('generate_order_sheet');
            Route::get('orders/{orderId}/pdf/order-signs', [\App\Http\Controllers\v2\PDFController::class, 'generateOrderSigns'])->name('generate_order_signs');
            Route::get('orders/{orderId}/pdf/order-packing-list', [\App\Http\Controllers\v2\PDFController::class, 'generateOrderPackingList'])->name('generate_order_packing_list');
            Route::get('orders/{orderId}/pdf/loading-note', [\App\Http\Controllers\v2\PDFController::class, 'generateLoadingNote'])->name('generate_loading_note');
            Route::get('orders/{orderId}/pdf/restricted-loading-note', [\App\Http\Controllers\v2\PDFController::class, 'generateRestrictedLoadingNote'])->name('generate_restricted_loading_note');
            Route::get('orders/{orderId}/pdf/order-cmr', [\App\Http\Controllers\v2\PDFController::class, 'generateOrderCMR'])->name('generate_order_cmr');
            Route::get('orders/{orderId}/xlsx/lots-report', [\App\Http\Controllers\v2\ExcelController::class, 'exportProductLotDetails'])->name('export_product_lot_details');
            Route::get('orders/{orderId}/xlsx/boxes-report', [\App\Http\Controllers\v2\ExcelController::class, 'exportBoxList'])->name('export_box_list');
            Route::get('orders/{orderId}/xls/A3ERP-sales-delivery-note', [\App\Http\Controllers\v2\ExcelController::class, 'exportA3ERPOrderSalesDeliveryNote'])->name('export_A3ERP_sales_delivery_note');
            Route::get('orders/xls/A3ERP-sales-delivery-note-filtered', [\App\Http\Controllers\v2\ExcelController::class, 'exportA3ERPOrderSalesDeliveryNoteWithFilters'])->name('export.a3erp.filtered');
            Route::get('orders/xls/facilcom-sales-delivery-note', [\App\Http\Controllers\v2\ExcelController::class, 'exportFacilcomOrderSalesDeliveryNoteWithFilters'])->name('export_facilcom_orders');
            Route::get('orders/{orderId}/xls/facilcom-single', [\App\Http\Controllers\v2\ExcelController::class, 'exportFacilcomSingleOrder'])->name('export_facilcom_single');
            Route::get('orders/xlsx/active-planned-products', [\App\Http\Controllers\v2\ExcelController::class, 'exportActiveOrderPlannedProducts'])->name('export_active_planned_products');

            /* Raw Material Receptions Facilcom Export */

            Route::get('orders/{orderId}/pdf/valued-loading-note', [\App\Http\Controllers\v2\PDFController::class, 'generateValuedLoadingNote'])->name('generate_valued_loading_note');
            /* generateIncident */
            Route::get('orders/{orderId}/pdf/incident', [\App\Http\Controllers\v2\PDFController::class, 'generateIncident'])->name('generate_incident');

            /* order confirmation */
            Route::get('orders/{orderId}/pdf/order-confirmation', [\App\Http\Controllers\v2\PDFController::class, 'generateOrderConfirmation'])->name('generate_order_confirmation');
            /* transport pickup request */
            Route::get('orders/{orderId}/pdf/transport-pickup-request', [\App\Http\Controllers\v2\PDFController::class, 'generateTransportPickupRequest'])->name('generate_transport_pickup_request');

            /* Envio de documentos */
            Route::post('orders/{orderId}/send-custom-documents', [OrderDocumentController::class, 'sendCustomDocumentation']);
            Route::post('orders/{orderId}/send-standard-documents', [OrderDocumentController::class, 'sendStandardDocumentation']);
        });
    });
});






//});
