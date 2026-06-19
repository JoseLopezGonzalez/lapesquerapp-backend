<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Controllers\v2\Traits\HandlesChromiumConfig;
use App\Http\Requests\v2\CloseSupplierLiquidationRequest;
use App\Http\Requests\v2\GenerateClosedLiquidationPdfRequest;
use App\Http\Requests\v2\GenerateLiquidationPdfRequest;
use App\Http\Requests\v2\GetLiquidationDetailsRequest;
use App\Http\Requests\v2\GetSuppliersLiquidationRequest;
use App\Http\Requests\v2\IndexSupplierLiquidationRequest;
use App\Http\Resources\v2\SupplierLiquidationResource;
use App\Models\Supplier;
use App\Models\SupplierLiquidation;
use App\Services\SupplierLiquidationService;
use Beganovich\Snappdf\Snappdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierLiquidationController extends Controller
{
    use HandlesChromiumConfig;

    public function index(IndexSupplierLiquidationRequest $request)
    {
        $this->authorize('viewAny', Supplier::class);

        $paginator = SupplierLiquidationService::list($request);

        return SupplierLiquidationResource::collection($paginator);
    }

    public function show(int $liquidationId)
    {
        $liquidation = SupplierLiquidation::with('supplier')->findOrFail($liquidationId);
        $this->authorize('view', $liquidation->supplier);

        $detail = SupplierLiquidationService::getDetail($liquidation);

        return response()->json($detail);
    }

    public function store(CloseSupplierLiquidationRequest $request)
    {
        $supplier = Supplier::findOrFail($request->input('supplier_id'));
        $this->authorize('update', $supplier);

        $liquidation = SupplierLiquidationService::closeLiquidation(
            $request->validated(),
            $request->user()->id
        );

        return response()->json([
            'data' => new SupplierLiquidationResource($liquidation),
        ], 201);
    }

    public function destroy(int $liquidationId)
    {
        $liquidation = SupplierLiquidation::findOrFail($liquidationId);
        $supplier = Supplier::findOrFail($liquidation->supplier_id);
        $this->authorize('update', $supplier);

        SupplierLiquidationService::reopenLiquidation($liquidation);

        return response()->json([
            'message' => 'Liquidación eliminada. Las recepciones y salidas de cebo han quedado disponibles de nuevo.',
        ]);
    }

    public function getSuppliers(GetSuppliersLiquidationRequest $request)
    {
        $this->authorize('viewAny', Supplier::class);

        $dates = $request->input('dates', []);
        $includeLiquidated = filter_var($request->input('include_liquidated', false), FILTER_VALIDATE_BOOLEAN);
        $onlyUnliquidated = filter_var($request->input('only_unliquidated', false), FILTER_VALIDATE_BOOLEAN);
        $result = SupplierLiquidationService::getSuppliersWithActivity($dates, $includeLiquidated, $onlyUnliquidated);

        return response()->json(['data' => $result]);
    }

    public function getDetails(GetLiquidationDetailsRequest $request, int $supplierId)
    {
        $supplier = Supplier::findOrFail($supplierId);
        $this->authorize('view', $supplier);

        $dates = $request->input('dates', []);
        $details = SupplierLiquidationService::getLiquidationDetails($supplierId, $dates);

        return response()->json($details);
    }

    /**
     * PDF de previsualización — para una liquidación aún no creada.
     * Recibe los IDs seleccionados y el rango de fechas.
     */
    public function previewPdf(GenerateLiquidationPdfRequest $request, int $supplierId): StreamedResponse
    {
        $supplier = Supplier::findOrFail($supplierId);
        $this->authorize('view', $supplier);

        $dates = $request->input('dates', []);
        $details = SupplierLiquidationService::getLiquidationDetails($supplierId, $dates);

        $selectedReceptionIds = array_map('intval', (array) $request->input('receptions', []));
        $selectedDispatchIds = array_map('intval', (array) $request->input('dispatches', []));

        [$filteredReceptions, $filteredDispatches] = SupplierLiquidationService::filterDetailsForPdf(
            $details,
            $selectedReceptionIds,
            $selectedDispatchIds
        );

        $summary = SupplierLiquidationService::calculateSummary($filteredReceptions, $filteredDispatches);
        $paymentTotals = $this->resolvePaymentTotals($request, $summary);
        $showTransferPayment = $this->resolveShowTransferPayment($request);

        $startDate = $dates['start'] ?? null;
        $endDate = $dates['end'] ?? null;
        $supplierName = $details['supplier']['name'] ?? 'Proveedor';
        $fileName = 'Liquidacion_Proveedor_' . str_replace([' ', '/', '\\'], '_', $supplierName) . '_' . $startDate . '_' . $endDate;

        return $this->streamPdf($details['supplier'], $details['date_range'], $filteredReceptions, $filteredDispatches, $summary, $paymentTotals, $showTransferPayment, $fileName, null, true);
    }

    /**
     * PDF de una liquidación ya creada — usa los registros vinculados por FK.
     */
    public function pdf(GenerateClosedLiquidationPdfRequest $request, int $liquidationId): StreamedResponse
    {
        $liquidation = SupplierLiquidation::with('supplier')->findOrFail($liquidationId);
        $this->authorize('view', $liquidation->supplier);

        $detail = SupplierLiquidationService::getDetail($liquidation);

        $summary = SupplierLiquidationService::calculateSummary($detail['receptions'], $detail['dispatches']);
        $paymentTotals = $this->resolvePaymentTotals($request, $summary);
        $showTransferPayment = $this->resolveShowTransferPayment($request);

        $startDate = $detail['liquidation']['start_date'];
        $endDate = $detail['liquidation']['end_date'];
        $supplierName = $detail['supplier']['name'] ?? 'Proveedor';
        $fileName = 'Liquidacion_Proveedor_' . str_replace([' ', '/', '\\'], '_', $supplierName) . '_' . $startDate . '_' . $endDate;

        $dateRange = ['start' => $startDate, 'end' => $endDate];

        return $this->streamPdf($detail['supplier'], $dateRange, $detail['receptions'], $detail['dispatches'], $summary, $paymentTotals, $showTransferPayment, $fileName, $liquidationId);
    }

    private function resolvePaymentTotals($request, array $summary): array
    {
        $paymentMethod = $request->input('payment_method');
        $hasManagementFee = filter_var(
            $request->input('has_management_fee'),
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        ) ?? false;

        return SupplierLiquidationService::calculatePaymentTotals($summary, $paymentMethod, $hasManagementFee);
    }

    private function resolveShowTransferPayment($request): bool
    {
        return filter_var(
            $request->input('show_transfer_payment'),
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        ) ?? true;
    }

    private function streamPdf(array $supplier, array $dateRange, array $receptions, array $dispatches, array $summary, array $paymentTotals, bool $showTransferPayment, string $fileName, ?int $liquidationId = null, bool $isPreview = false): StreamedResponse
    {
        $html = view('pdf.v2.supplier_liquidations.liquidation', [
            'supplier' => $supplier,
            'date_range' => $dateRange,
            'receptions' => $receptions,
            'dispatches' => $dispatches,
            'summary' => $summary,
            'payment_totals' => $paymentTotals,
            'show_transfer_payment' => $showTransferPayment,
            'liquidation_id' => $liquidationId,
            'is_preview' => $isPreview,
        ])->render();

        $snappdf = new Snappdf();
        $this->configureChromium($snappdf);
        $pdf = $snappdf->setHtml($html)->generate();

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, "{$fileName}.pdf", ['Content-Type' => 'application/pdf']);
    }
}
