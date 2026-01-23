<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StandardOrderDocuments extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $subjectText;
    public $markdownTemplate; // Cambia según destinatario
    public $documents;

    public function __construct($order, $subjectText, $markdownTemplate, $documents)
    {
        $this->order = $order;
        $this->subjectText = $subjectText;
        $this->markdownTemplate = $markdownTemplate;
        $this->documents = $documents;
    }

    public function build()
    {
        // Obtener configuración de remitente del tenant
        $mailConfigService = app(\App\Services\TenantMailConfigService::class);
        $fromAddress = $mailConfigService->getFromAddress();
        $fromName = $mailConfigService->getFromName();

        $email = $this->subject($this->subjectText)
                      ->from($fromAddress, $fromName)
                      ->markdown($this->markdownTemplate, [
                          'order' => $this->order
                      ]);

        foreach ($this->documents as $document) {
            $email->attach($document['path'], [
                'as' => $document['name'],
                'mime' => 'application/pdf',
            ]);
        }

        return $email;
    }
}
