<?php

use App\Http\Controllers\Public\TenantController;
use App\Http\Controllers\v2\OrderDocumentController;
use App\Http\Controllers\v2\PdfExtractionController;
use App\Http\Controllers\v2\SettingController;
use App\Http\Controllers\v2\TaxController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\v2\ActivityLogController;
use App\Http\Controllers\v2\AuthController as V2AuthController;
use App\Http\Controllers\v2\BoxesController;
use App\Http\Controllers\v2\CaptureZoneController as V2CaptureZoneController;
use App\Http\Controllers\v2\CeboDispatchController as V2CeboDispatchController;
use App\Http\Controllers\v2\CeboDispatchStatisticsController;
use App\Http\Controllers\v2\CountryController;
use App\Http\Controllers\v2\CustomerController as V2CustomerController;
use App\Http\Controllers\v2\FishingGearController;
use App\Http\Controllers\v2\IncidentController;
use App\Http\Controllers\v2\IncotermController as V2IncotermController;
use App\Http\Controllers\v2\LabelController;
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
use App\Http\Controllers\v2\SupplierLiquidationController;
use App\Http\Controllers\v2\TransportController as V2TransportController;
use App\Http\Controllers\v2\UserController;
use App\Http\Controllers\v2\ProcessController as V2ProcessController;
use App\Http\Controllers\v2\ProductionController as V2ProductionController;
use App\Http\Controllers\v2\ProductionRecordController;
use App\Http\Controllers\v2\ProductionInputController;
use App\Http\Controllers\v2\ProductionOutputController;
use App\Http\Controllers\v2\PunchController;
use App\Http\Controllers\v2\EmployeeController;
use App\Http\Controllers\v2\ProductionOutputConsumptionController;

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


/*
|--------------------------------------------------------------------------
| Health check (para verificación Sail / frontend)
|--------------------------------------------------------------------------
*/
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
        'env' => config('app.env'),
    ], 200);
})->name('api.health');

Route::get('/test-cors', function (Request $request) {
    return response()->json(['message' => 'CORS funciona correctamente!'], 200)
        ->header('Access-Control-Allow-Origin', $request->header('Origin'))
        ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, PUT, DELETE')
        ->header('Access-Control-Allow-Headers', 'Origin, Content-Type, Authorization')
        ->header('Access-Control-Allow-Credentials', 'true');
});












/* Api V2 */
/* Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('v2/login', [V2AuthController::class, 'login'])->name('login');
    Route::post('v2/logout', [V2AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('v2/me', [V2AuthController::class, 'me'])->middleware('auth:sanctum');
    Route::apiResource('v2/orders', V2OrderController::class);
    Route::apiResource('v2/raw-material-receptions', V2RawMaterialReceptionController::class);
    Route::get('v2/orders_report', [OrdersReportController::class, 'exportToExcel'])->name('export.orders');
}); */

/* IMPORTANTISIMO - Resolución de tenant por subdominio (público, sin auth) */
Route::get('v2/public/tenant/{subdomain}', [TenantController::class, 'showBySubdomain'])
    ->middleware('throttle:60,1')
    ->name('api.v2.public.tenant');

/* Comprobar el tenant ya que esta aplicado de manera global */
Route::group(['prefix' => 'v2', 'as' => 'v2.', 'middleware' => ['tenant']], function () {
    // Rutas públicas (sin autenticación)
    Route::post('login', [V2AuthController::class, 'login'])->middleware('throttle:5,1')->name('v2.login');
    Route::post('logout', [V2AuthController::class, 'logout'])->middleware('auth:sanctum')->name('v2.logout');
    Route::get('me', [V2AuthController::class, 'me'])->middleware('auth:sanctum')->name('v2.me');

    // Acceso por email: un solo botón "Acceder" → un email con magic link + código OTP (throttle para evitar abuso)
    Route::post('auth/request-access', [V2AuthController::class, 'requestAccess'])->middleware('throttle:5,1')->name('v2.auth.request-access');
    Route::post('auth/magic-link/request', [V2AuthController::class, 'requestMagicLink'])->middleware('throttle:5,1')->name('v2.auth.magic-link.request');
    Route::post('auth/magic-link/verify', [V2AuthController::class, 'verifyMagicLink'])->middleware('throttle:10,1')->name('v2.auth.magic-link.verify');
    Route::post('auth/otp/request', [V2AuthController::class, 'requestOtp'])->middleware('throttle:5,1')->name('v2.auth.otp.request');
    Route::post('auth/otp/verify', [V2AuthController::class, 'verifyOtp'])->middleware('throttle:10,1')->name('v2.auth.otp.verify');

    Route::get('/customers/op', [V2CustomerController::class, 'options']);

    // Fichajes (público - para dispositivos NFC)
    Route::post('punches', [PunchController::class, 'store'])->name('v2.punches.store');

    // Rutas protegidas por Sanctum — por ahora todas accesibles para todos los roles (luego: policies y restricciones)
        Route::middleware(['auth:sanctum', 'role:tecnico,administrador,direccion,administracion,comercial,operario'])->group(function () {
            /* Options (sistema) */
            Route::get('roles/options', [RoleController::class, 'options']);
            Route::get('users/options', [UserController::class, 'options']);

            /* Descargas */
            Route::get('orders_report', [OrdersReportController::class, 'exportToExcel'])->name('export.orders');

            /* Controladores de sistema */
            Route::apiResource('sessions', SessionController::class)->only(['index', 'destroy']);
            Route::post('users/{user}/resend-invitation', [UserController::class, 'resendInvitation'])->name('v2.users.resend-invitation');
            Route::apiResource('users', UserController::class);
            Route::apiResource('activity-logs', ActivityLogController::class);

            /* Options (catálogos y resto) */
            Route::get('settings', [SettingController::class, 'index']);
            Route::put('settings', [SettingController::class, 'update']);

            Route::get('/customers/options', [V2CustomerController::class, 'options']);
            Route::get('/salespeople/options', [V2SalespersonController::class, 'options']);
            Route::get('/employees/options', [EmployeeController::class, 'options']);
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
            Route::get('/pallets/registered', [V2PalletController::class, 'registeredPallets']);
            Route::get('/pallets/search-by-lot', [V2PalletController::class, 'searchByLot']);
            Route::get('/orders/{orderId}/available-pallets', [V2PalletController::class, 'availableForOrder']);
            Route::get('/stores/options', [V2StoreController::class, 'options']);
            Route::get('/orders/options', [V2OrderController::class, 'options']);
            Route::post('/pallets/assign-to-position', [V2PalletController::class, 'assignToPosition']);
            Route::post('/pallets/move-to-store', [V2PalletController::class, 'moveToStore']);
            Route::post('/pallets/move-multiple-to-store', [V2PalletController::class, 'moveMultipleToStore']);
            Route::post('pallets/{id}/unassign-position', [V2PalletController::class, 'unassignPosition']);
            Route::post('/pallets/{id}/link-order', [V2PalletController::class, 'linkOrder']);
            Route::post('/pallets/link-orders', [V2PalletController::class, 'linkOrders']);
            Route::post('/pallets/{id}/unlink-order', [V2PalletController::class, 'unlinkOrder']);
            Route::post('/pallets/unlink-orders', [V2PalletController::class, 'unlinkOrders']);




            /* Active Orders - For Order Manager */
            Route::get('/orders/active', [V2OrderController::class, 'active']);
            
            /* Active Order Options */
            Route::get('/active-orders/options', [V2OrderController::class, 'activeOrdersOptions']);
            
            /* Production View - Orders grouped by product */
            Route::get('/orders/production-view', [V2OrderController::class, 'productionView']);
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

            /* bulkUpdateState */
            Route::post('pallets/update-state', [V2PalletController::class, 'bulkUpdateState'])->name('pallets.bulk_update_state');

            /* Controladores Genericos */
            Route::apiResource('employees', EmployeeController::class);
            Route::delete('employees', [EmployeeController::class, 'destroyMultiple']);
            // Rutas específicas de punches deben ir ANTES del apiResource
            Route::get('punches/dashboard', [PunchController::class, 'dashboard'])->name('punches.dashboard');
            Route::get('punches/statistics', [PunchController::class, 'statistics'])->name('punches.statistics');
            Route::get('punches/calendar', [PunchController::class, 'calendar'])->name('punches.calendar');
            // Fichajes manuales (requieren autenticación)
            // Nota: POST /api/v2/punches detecta automáticamente si es manual (tiene timestamp/event_type) o NFC
            // Las rutas bulk solo son para fichajes manuales
            Route::post('punches/bulk/validate', [PunchController::class, 'bulkValidate'])->name('punches.bulk.validate');
            Route::post('punches/bulk', [PunchController::class, 'bulkStore'])->name('punches.bulk.store');
            Route::apiResource('punches', PunchController::class)->except(['store', 'create']);
            Route::delete('punches', [PunchController::class, 'destroyMultiple']);
            Route::apiResource('orders', V2OrderController::class);
            Route::delete('orders', [V2OrderController::class, 'destroyMultiple']);
            Route::apiResource('order-planned-product-details', OrderPlannedProductDetailController::class);
            
            /* Raw Material Receptions - Export debe ir ANTES del apiResource */
            Route::get('raw-material-receptions/facilcom-xls', [\App\Http\Controllers\v2\ExcelController::class, 'exportRawMaterialReceptionFacilcom'])->name('export_raw_material_receptions_facilcom');
            Route::get('raw-material-receptions/a3erp-xls', [\App\Http\Controllers\v2\ExcelController::class, 'exportRawMaterialReceptionA3erp'])->name('export_raw_material_receptions_a3erp');
            /* receptionChartData */
            Route::get('raw-material-receptions/reception-chart-data', [RawMaterialReceptionStatisticsController::class, 'receptionChartData']);
            /* Bulk update declared data - debe ir ANTES del apiResource */
            Route::post('raw-material-receptions/validate-bulk-update-declared-data', [V2RawMaterialReceptionController::class, 'validateBulkUpdateDeclaredData'])->name('raw-material-receptions.validate-bulk-update-declared-data');
            Route::post('raw-material-receptions/bulk-update-declared-data', [V2RawMaterialReceptionController::class, 'bulkUpdateDeclaredData'])->name('raw-material-receptions.bulk-update-declared-data');
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
            Route::delete('stores', [V2StoreController::class, 'deleteMultiple']);

            Route::apiResource('payment-terms', V2PaymentTermController::class);
            Route::delete('payment-terms', [V2PaymentTermController::class, 'destroyMultiple']);

            Route::apiResource('countries', CountryController::class);
            Route::delete('countries', [CountryController::class, 'destroyMultiple']);

            Route::get('boxes/xlsx', [\App\Http\Controllers\v2\ExcelController::class, 'exportBoxesReport'])->name('export_boxes_report');

            Route::apiResource('boxes', BoxesController::class); /* Algo raro en el nombre */
            Route::delete('boxes', [BoxesController::class, 'destroyMultiple']);
            Route::apiResource('pallets', V2PalletController::class);
            Route::delete('pallets', [V2PalletController::class, 'destroyMultiple']);
            Route::apiResource('customers', V2CustomerController::class);
            Route::delete('customers', [V2CustomerController::class, 'destroyMultiple']);
            Route::get('customers/{customer}/order-history', [V2CustomerController::class, 'getOrderHistory'])->name('customers.order_history');

            Route::apiResource('suppliers', V2SupplierController::class);
            Route::delete('suppliers', [V2SupplierController::class, 'destroyMultiple']);

            /* Supplier Liquidations */
            Route::get('supplier-liquidations/suppliers', [SupplierLiquidationController::class, 'getSuppliers'])->name('supplier-liquidations.suppliers');
            Route::get('supplier-liquidations/{supplierId}/details', [SupplierLiquidationController::class, 'getDetails'])->name('supplier-liquidations.details');
            Route::get('supplier-liquidations/{supplierId}/pdf', [SupplierLiquidationController::class, 'generatePdf'])->name('supplier-liquidations.pdf');

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
            Route::get('cebo-dispatches/a3erp2-xlsx', [\App\Http\Controllers\v2\ExcelController::class, 'exportCeboDispatchA3erp2'])->name('export_cebo_dispatches_a3erp2');
            /* dispatchChartData */
            Route::get('cebo-dispatches/dispatch-chart-data', [CeboDispatchStatisticsController::class, 'dispatchChartData']);
            Route::apiResource('cebo-dispatches', V2CeboDispatchController::class);
            Route::delete('cebo-dispatches', [V2CeboDispatchController::class, 'destroyMultiple']);
            Route::get('labels/options', [LabelController::class, 'options'])->name('labels.options');
            Route::post('labels/{label}/duplicate', [LabelController::class, 'duplicate'])->name('labels.duplicate');
            Route::apiResource('labels', LabelController::class);

            /* Production Module v2 */
            Route::apiResource('productions', V2ProductionController::class);
            Route::delete('productions', [V2ProductionController::class, 'destroyMultiple']);
            Route::get('productions/{id}/diagram', [V2ProductionController::class, 'getDiagram'])->name('productions.getDiagram');
            Route::get('productions/{id}/process-tree', [V2ProductionController::class, 'getProcessTree'])->name('productions.getProcessTree');
            Route::get('productions/{id}/totals', [V2ProductionController::class, 'getTotals'])->name('productions.getTotals');
            Route::get('productions/{id}/reconciliation', [V2ProductionController::class, 'getReconciliation'])->name('productions.getReconciliation');
            Route::get('productions/{id}/available-products-for-outputs', [V2ProductionController::class, 'getAvailableProductsForOutputs'])->name('productions.getAvailableProductsForOutputs');

            Route::get('production-records/options', [ProductionRecordController::class, 'options'])->name('production-records.options');
            Route::get('production-records/{id}/sources-data', [ProductionRecordController::class, 'getSourcesData'])->name('production-records.sources-data');
            Route::apiResource('production-records', ProductionRecordController::class);
            Route::get('production-records/{id}/tree', [ProductionRecordController::class, 'tree'])->name('production-records.tree');
            Route::post('production-records/{id}/finish', [ProductionRecordController::class, 'finish'])->name('production-records.finish');
            Route::put('production-records/{id}/outputs', [ProductionRecordController::class, 'syncOutputs'])->name('production-records.syncOutputs');
            Route::put('production-records/{id}/parent-output-consumptions', [ProductionRecordController::class, 'syncConsumptions'])->name('production-records.syncConsumptions');

            // Las rutas específicas deben ir ANTES del apiResource para evitar conflictos
            Route::post('production-inputs/multiple', [ProductionInputController::class, 'storeMultiple'])->name('production-inputs.storeMultiple');
            Route::delete('production-inputs/multiple', [ProductionInputController::class, 'destroyMultiple'])->name('production-inputs.destroyMultiple');
            Route::apiResource('production-inputs', ProductionInputController::class);

            // Production Outputs - Costes y Trazabilidad
            Route::get('production-outputs/{id}/cost-breakdown', [ProductionOutputController::class, 'getCostBreakdown'])->name('production-outputs.cost-breakdown');
            Route::apiResource('production-outputs', ProductionOutputController::class);
            Route::post('production-outputs/multiple', [ProductionOutputController::class, 'storeMultiple'])->name('production-outputs.storeMultiple');

            // Cost Catalog
            Route::apiResource('cost-catalog', \App\Http\Controllers\v2\CostCatalogController::class);

            // Production Costs
            Route::apiResource('production-costs', \App\Http\Controllers\v2\ProductionCostController::class);

            Route::apiResource('production-output-consumptions', ProductionOutputConsumptionController::class);
            Route::post('production-output-consumptions/multiple', [ProductionOutputConsumptionController::class, 'storeMultiple'])->name('production-output-consumptions.storeMultiple');
            Route::get('production-output-consumptions/available-outputs/{productionRecordId}', [ProductionOutputConsumptionController::class, 'getAvailableOutputs'])->name('production-output-consumptions.available-outputs');

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
            Route::get('orders/pdf/order-sheets-filtered', [\App\Http\Controllers\v2\PDFController::class, 'generateOrderSheetsWithFilters'])->name('generate_order_sheets_filtered');
            Route::get('orders/{orderId}/pdf/order-signs', [\App\Http\Controllers\v2\PDFController::class, 'generateOrderSigns'])->name('generate_order_signs');
            Route::get('orders/{orderId}/pdf/order-packing-list', [\App\Http\Controllers\v2\PDFController::class, 'generateOrderPackingList'])->name('generate_order_packing_list');
            Route::get('orders/{orderId}/pdf/loading-note', [\App\Http\Controllers\v2\PDFController::class, 'generateLoadingNote'])->name('generate_loading_note');
            Route::get('orders/{orderId}/pdf/restricted-loading-note', [\App\Http\Controllers\v2\PDFController::class, 'generateRestrictedLoadingNote'])->name('generate_restricted_loading_note');
            Route::get('orders/{orderId}/pdf/order-cmr', [\App\Http\Controllers\v2\PDFController::class, 'generateOrderCMR'])->name('generate_order_cmr');
            Route::get('orders/{orderId}/xlsx/lots-report', [\App\Http\Controllers\v2\ExcelController::class, 'exportProductLotDetails'])->name('export_product_lot_details');
            Route::get('orders/{orderId}/xlsx/boxes-report', [\App\Http\Controllers\v2\ExcelController::class, 'exportBoxList'])->name('export_box_list');
            Route::get('orders/{orderId}/xls/A3ERP-sales-delivery-note', [\App\Http\Controllers\v2\ExcelController::class, 'exportA3ERPOrderSalesDeliveryNote'])->name('export_A3ERP_sales_delivery_note');
            Route::get('orders/xls/A3ERP-sales-delivery-note-filtered', [\App\Http\Controllers\v2\ExcelController::class, 'exportA3ERPOrderSalesDeliveryNoteWithFilters'])->name('export.a3erp.filtered');
            Route::get('orders/{orderId}/xls/A3ERP2-sales-delivery-note', [\App\Http\Controllers\v2\ExcelController::class, 'exportA3ERP2OrderSalesDeliveryNote'])->name('export_A3ERP2_sales_delivery_note');
            Route::get('orders/xls/A3ERP2-sales-delivery-note-filtered', [\App\Http\Controllers\v2\ExcelController::class, 'exportA3ERP2OrderSalesDeliveryNoteWithFilters'])->name('export.a3erp2.filtered');
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




//});
