<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Beganovich\Snappdf\Snappdf;

class TransportShipmentDetails extends Mailable
{
    use Queueable, SerializesModels;

    public $order;

    /**
     * Create a new message instance.
     *
     * @param mixed $order Los datos del pedido.
     */
    public function __construct($order)
    {
        $this->order = $order;
    }

    public function build()
    {

        $snappdf = new Snappdf();
        $html = view('pdf.delivery_note', ['order' => $this->order])->render();
        
        // Configure Chromium using centralized configuration
        $this->configureChromium($snappdf);

        $pdfContent = $snappdf->setHtml($html)->generate();

        /* NUEVO */



        /* $pdfContent = $snappdf->generate(); */

        // Guarda temporalmente el PDF para adjuntarlo
        $pdfPath = storage_path('app/public/delivery-note-' . $this->order->id . '.pdf');
        file_put_contents($pdfPath, $pdfContent);

        // Obtener configuración de remitente del tenant
        $mailConfigService = app(\App\Services\TenantMailConfigService::class);
        $fromAddress = $mailConfigService->getFromAddress();
        $fromName = $mailConfigService->getFromName();

        return $this->subject('Detalle mercancía ' . /* formated date */ date('d/m/Y', strtotime($this->order->load_date)) . ' - ' . $this->order->customer->name)
            ->from($fromAddress, $fromName)
            ->markdown('emails.orders.transport_details', [
                'order' => $this->order,
            ])
            ->attach($pdfPath, [
                'as' => 'Delivery-note-' . $this->order->formattedId . '.pdf',
                'mime' => 'application/pdf',
            ]);
    }

    /**
     * Configure Chromium/Chrome path and arguments for PDF generation.
     *
     * @param Snappdf $snappdf
     * @param array $additionalArguments Additional arguments to add (optional)
     * @return void
     */
    private function configureChromium(Snappdf $snappdf, array $additionalArguments = []): void
    {
        // Set Chromium path from configuration
        $chromiumPath = config('pdf.chromium.path', '/usr/bin/google-chrome');
        $snappdf->setChromiumPath($chromiumPath);

        // Apply margins from configuration
        $margins = config('pdf.chromium.margins', []);
        if (isset($margins['top'])) {
            $snappdf->addChromiumArguments('--margin-top=' . $margins['top']);
        }
        if (isset($margins['right'])) {
            $snappdf->addChromiumArguments('--margin-right=' . $margins['right']);
        }
        if (isset($margins['bottom'])) {
            $snappdf->addChromiumArguments('--margin-bottom=' . $margins['bottom']);
        }
        if (isset($margins['left'])) {
            $snappdf->addChromiumArguments('--margin-left=' . $margins['left']);
        }

        // Apply default arguments from configuration
        $defaultArguments = config('pdf.chromium.arguments', []);
        foreach ($defaultArguments as $argument) {
            $snappdf->addChromiumArguments($argument);
        }

        // Apply additional arguments if provided
        foreach ($additionalArguments as $argument) {
            $snappdf->addChromiumArguments($argument);
        }
    }
}
