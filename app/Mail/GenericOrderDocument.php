<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GenericOrderDocument extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $view;
    public $subjectText;
    public $documentName;
    public $attachmentPath; // ✅ Nueva propiedad

    public function __construct($order, $view, $subjectText, $documentName, $attachmentPath = null)
    {
        $this->order = $order;
        $this->view = $view;
        $this->subjectText = $subjectText;
        $this->documentName = $documentName;
        $this->attachmentPath = $attachmentPath;
    }

    public function build()
    {
        // Obtener configuración de remitente del tenant
        $mailConfigService = app(\App\Services\TenantMailConfigService::class);
        $fromAddress = $mailConfigService->getFromAddress();
        $fromName = $mailConfigService->getFromName();

        $email = $this->subject($this->subjectText)
            ->from($fromAddress, $fromName)
            ->markdown($this->view, [
                'order' => $this->order,
                'documentName' => $this->documentName
            ]);

        // ✅ Adjuntar si hay
        if ($this->attachmentPath && file_exists($this->attachmentPath)) {
            $email->attach($this->attachmentPath, [
                'as' => "{$this->documentName} - Pedido {$this->order->formattedId}.pdf",
                'mime' => 'application/pdf',
            ]);
        }

        return $email;
    }
}

