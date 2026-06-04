<?php

namespace App\Http\Controllers\v2;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Controllers\v2\Traits\HandlesChromiumConfig;
use App\Http\Requests\v2\PalletExpeditionLabelsRequest;
use App\Models\Pallet;
use App\Services\v2\PalletExpeditionLabelService;
use Beganovich\Snappdf\Snappdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PalletExpeditionLabelController extends Controller
{
    use HandlesChromiumConfig;

    public function __construct(
        private readonly PalletExpeditionLabelService $labelService
    ) {}

    public function show(Pallet $pallet): StreamedResponse
    {
        $this->authorizePalletLabel($pallet);

        return $this->generatePdf(
            collect([$this->labelService->labelForSinglePallet($pallet)]),
            'Etiqueta_expedicion_palet_'.$pallet->id
        );
    }

    public function store(PalletExpeditionLabelsRequest $request): StreamedResponse
    {
        $pallets = Pallet::query()
            ->whereIn('id', $request->validated('palletIds'))
            ->get();

        $pallets->each(fn (Pallet $pallet) => $this->authorize('view', $pallet));

        return $this->generatePdf(
            $this->labelService->labelsForPallets($pallets),
            'Etiquetas_expedicion_palets_'.now()->format('Y-m-d_His')
        );
    }

    private function authorizePalletLabel(Pallet $pallet): void
    {
        if (auth()->user()->hasRole(Role::Comercial->value)) {
            abort(403);
        }

        $this->authorize('view', $pallet);
    }

    private function generatePdf(mixed $labels, string $fileName): StreamedResponse
    {
        $snappdf = new Snappdf;
        $html = view('pdf.v2.orders.pallet_expedition_labels', ['labels' => $labels])->render();

        $this->configureChromium($snappdf, [
            '--margin-top=0',
            '--margin-right=0',
            '--margin-bottom=0',
            '--margin-left=0',
        ]);

        $pdf = $snappdf->setHtml($html)->generate();

        return response()->streamDownload(fn () => print $pdf, "{$fileName}.pdf", ['Content-Type' => 'application/pdf']);
    }
}
