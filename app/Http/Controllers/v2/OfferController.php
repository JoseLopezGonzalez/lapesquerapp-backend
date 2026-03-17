<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Controllers\v2\Traits\HandlesChromiumConfig;
use App\Http\Requests\v2\CreateOrderFromOfferRequest;
use App\Http\Requests\v2\IndexOfferRequest;
use App\Http\Requests\v2\OfferSendRequest;
use App\Http\Requests\v2\RejectOfferRequest;
use App\Http\Requests\v2\StoreOfferRequest;
use App\Http\Requests\v2\UpdateOfferRequest;
use App\Http\Resources\v2\OfferResource;
use App\Http\Resources\v2\OrderDetailsResource;
use App\Mail\OfferMail;
use App\Models\Offer;
use App\Services\v2\OfferService;
use Beganovich\Snappdf\Snappdf;
use Illuminate\Support\Facades\Mail;

class OfferController extends Controller
{
    use HandlesChromiumConfig;

    public function index(IndexOfferRequest $request)
    {
        return OfferResource::collection(OfferService::list($request));
    }

    public function store(StoreOfferRequest $request)
    {
        $this->authorize('create', Offer::class);
        $offer = OfferService::store($request->validated(), $request->user());

        return response()->json([
            'message' => 'Oferta creada correctamente.',
            'data' => new OfferResource($offer),
        ], 201);
    }

    public function show(string $id)
    {
        $offer = Offer::with(['prospect.country', 'prospect.primaryContact', 'customer.country', 'salesperson', 'incoterm', 'paymentTerm', 'order.offer', 'lines.product', 'lines.tax'])
            ->findOrFail($id);
        $this->authorize('view', $offer);

        return response()->json([
            'data' => new OfferResource($offer),
        ]);
    }

    public function update(UpdateOfferRequest $request, string $id)
    {
        $offer = Offer::with(['lines'])->findOrFail($id);
        $this->authorize('update', $offer);
        $offer = OfferService::update($offer, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Oferta actualizada correctamente.',
            'data' => new OfferResource($offer),
        ]);
    }

    public function destroy(string $id)
    {
        $offer = Offer::findOrFail($id);
        $this->authorize('delete', $offer);
        OfferService::delete($offer);

        return response()->json(['message' => 'Oferta eliminada correctamente.']);
    }

    public function send(OfferSendRequest $request, string $id)
    {
        $offer = Offer::with(['prospect.primaryContact', 'customer', 'lines.product', 'lines.tax', 'salesperson', 'incoterm', 'paymentTerm'])->findOrFail($id);
        $this->authorize('update', $offer);
        $validated = $request->validated();
        $offer = OfferService::markAsSent($offer, $validated['channel']);

        if ($validated['channel'] === Offer::SEND_CHANNEL_EMAIL) {
            $pdf = $this->buildOfferPdf($offer);
            Mail::to($validated['email'] ?? $offer->prospect?->primaryContact?->email)
                ->send(new OfferMail(
                    $offer,
                    $validated['subject'] ?? ('Oferta #'.$offer->id),
                    $pdf
                ));
        }

        return response()->json([
            'message' => 'Oferta enviada correctamente.',
            'data' => new OfferResource($offer),
        ]);
    }

    public function accept(string $id)
    {
        $offer = Offer::findOrFail($id);
        $this->authorize('update', $offer);
        $offer = OfferService::accept($offer);

        return response()->json([
            'message' => 'Oferta aceptada correctamente.',
            'data' => new OfferResource($offer),
        ]);
    }

    public function reject(RejectOfferRequest $request, string $id)
    {
        $offer = Offer::findOrFail($id);
        $this->authorize('update', $offer);
        $offer = OfferService::reject($offer, $request->validated()['reason']);

        return response()->json([
            'message' => 'Oferta rechazada correctamente.',
            'data' => new OfferResource($offer),
        ]);
    }

    public function expire(string $id)
    {
        $offer = Offer::findOrFail($id);
        $this->authorize('update', $offer);
        $offer = OfferService::expire($offer);

        return response()->json([
            'message' => 'Oferta marcada como expirada.',
            'data' => new OfferResource($offer),
        ]);
    }

    public function pdf(string $id)
    {
        $offer = Offer::with(['prospect.primaryContact', 'customer', 'salesperson', 'incoterm', 'paymentTerm', 'lines.product', 'lines.tax'])->findOrFail($id);
        $this->authorize('view', $offer);

        return response()->streamDownload(
            fn () => print $this->buildOfferPdf($offer),
            'oferta-'.$offer->id.'.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    public function whatsappText(string $id)
    {
        $offer = Offer::with(['lines.product', 'prospect', 'customer'])->findOrFail($id);
        $this->authorize('view', $offer);

        return response()->json([
            'data' => [
                'text' => OfferService::buildWhatsappText($offer),
            ],
        ]);
    }

    public function email(OfferSendRequest $request, string $id)
    {
        $offer = Offer::with(['prospect.primaryContact', 'customer', 'lines.product', 'lines.tax', 'salesperson', 'incoterm', 'paymentTerm'])->findOrFail($id);
        $this->authorize('update', $offer);
        $validated = $request->validated();

        $pdf = $this->buildOfferPdf($offer);
        Mail::to($validated['email'] ?? $offer->prospect?->primaryContact?->email)
            ->send(new OfferMail(
                $offer,
                $validated['subject'] ?? ('Oferta #'.$offer->id),
                $pdf
            ));

        $offer = OfferService::markAsSent($offer, Offer::SEND_CHANNEL_EMAIL);

        return response()->json([
            'message' => 'Oferta enviada por email correctamente.',
            'data' => new OfferResource($offer),
        ]);
    }

    public function createOrder(CreateOrderFromOfferRequest $request, string $id)
    {
        $offer = Offer::with(['prospect.primaryContact', 'customer', 'lines'])->findOrFail($id);
        $this->authorize('update', $offer);
        $order = OfferService::createOrderFromAcceptedOffer($offer, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Pedido creado desde la oferta correctamente.',
            'data' => new OrderDetailsResource($order),
        ], 201);
    }

    private function buildOfferPdf(Offer $offer): string
    {
        $snappdf = new Snappdf;
        $html = view('pdf.v2.offers.offer', ['offer' => $offer])->render();
        $this->configureChromium($snappdf);

        return $snappdf->setHtml($html)->generate();
    }
}
