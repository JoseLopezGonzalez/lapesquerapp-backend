<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ImpersonationRequestEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $tenantName,
        public string $approveUrl,
        public string $rejectUrl
    ) {}

    public function build()
    {
        return $this->subject('Solicitud de acceso a tu cuenta — PesquerApp')
            ->from(
                config('mail.from.address', 'noreply@lapesquerapp.es'),
                config('mail.from.name', 'PesquerApp')
            )
            ->view('emails.impersonation-request', [
                'tenantName' => $this->tenantName,
                'approveUrl' => $this->approveUrl,
                'rejectUrl' => $this->rejectUrl,
            ]);
    }
}
