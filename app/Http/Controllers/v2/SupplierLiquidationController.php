<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Controllers\v2\Traits\HandlesChromiumConfig;
use App\Http\Requests\v2\GenerateLiquidationPdfRequest;
use App\Http\Requests\v2\GetLiquidationDetailsRequest;
use App\Http\Requests\v2\GetSuppliersLiquidationRequest;
use App\Models\Supplier;
use App\Services\SupplierLiquidationService;
use Beganovich\Snappdf\Snappdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierLiquidationController extends Controller
{
    use HandlesChromiumConfig;

    public function getSuppliers(GetSuppliersLiquidationRequest $request)
    {
        $this->authorize('viewAny', Supplier::class);

        $dates = $request->input('dates', []);
        $result = SupplierLiquidationService::getSuppliersWithActivity($dates);

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

    public function generatePdf(GenerateLiquidationPdfRequest $request, int $supplierId): StreamedResponse
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

        $paymentMethod = $request->input('payment_method');
        $hasManagementFee = filter_var(
            $request->input('has_management_fee'),
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        ) ?? false;

        $paymentTotals = SupplierLiquidationService::calculatePaymentTotals($summary, $paymentMethod, $hasManagementFee);

        $showTransferPayment = filter_var(
            $request->input('show_transfer_payment'),
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );
        $showTransferPayment = $showTransferPayment ?? true;

        $startDate = $dates['start'] ?? null;
        $endDate = $dates['end'] ?? null;
        $supplierName = $details['supplier']['name'] ?? 'Proveedor';
        $fileName = 'Liquidacion_Proveedor_' . str_replace([' ', '/', '\\'], '_', $supplierName) . '_' . $startDate . '_' . $endDate;

        $html = view('pdf.v2.supplier_liquidations.liquidation', [
            'supplier' => $details['supplier'],
            'date_range' => $details['date_range'],
            'receptions' => $filteredReceptions,
            'dispatches' => $filteredDispatches,
            'summary' => $summary,
            'payment_totals' => $paymentTotals,
            'show_transfer_payment' => $showTransferPayment,
        ])->render();

        $snappdf = new Snappdf();
        $this->configureChromium($snappdf);
        $pdf = $snappdf->setHtml($html)->generate();

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, "{$fileName}.pdf", ['Content-Type' => 'application/pdf']);
    }
}
