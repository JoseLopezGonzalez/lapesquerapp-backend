<?php

namespace App\Mail;

use PDF; // Al principio de tu archivo PHP donde necesitas usar DomPDF


use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Beganovich\Snappdf\Snappdf;



class OrderShipped extends Mailable
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

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {


        /* // Usar Browsershot para generar el PDF
            $html = view('pdf.delivery_note', ['order' => $this->order])->render();
            $pdf = Browsershot::html($html)
                ->format('A4')
                ->showBackground()
                ->margins(10, 10, 10, 10)
                ->pdf();
    
            // Guarda temporalmente el PDF para adjuntarlo
            $pdfPath = storage_path('app/public/delivery-note-' . $this->order->id . '.pdf');
            file_put_contents($pdfPath, $pdf);
    
            return $this->subject('Order Shipped: #' . $this->order->id)
                        ->markdown('emails.orders.shipped', [
                            'order' => $this->order,
                        ])
                        ->attach($pdfPath, [
                            'as' => 'delivery-note-' . $this->order->id . '.pdf',
                            'mime' => 'application/pdf',
                        ]);
         */

        /*  $pdf = PDF::loadView('pdf.delivery_note', ['order' => $this->order])->output();

        return $this->subject('Order Shipped: #' . $this->order->id)
                    ->markdown('emails.orders.shipped', [
                        'customer_name' => $this->order->customer->name,
                        'order_id' => $this->order->id,
                        'order' => $this->order,
                    ])
                    ->attachData($pdf, 'delivery-note-' . $this->order->id . '.pdf', [
                        'mime' => 'application/pdf',
                    ]); */


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

        return $this->subject('Order Shipped: #' . $this->order->id)
            ->from($fromAddress, $fromName)
            ->markdown('emails.orders.shipped', [
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
