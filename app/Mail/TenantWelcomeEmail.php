<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TenantWelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $companyName,
        public string $tenantUrl,
        public string $adminEmail
    ) {}

    public function build()
    {
        return $this->subject("Bienvenido a PesquerApp — {$this->companyName}")
            ->from(
                config('mail.from.address', 'noreply@lapesquerapp.es'),
                config('mail.from.name', 'PesquerApp')
            )
            ->view('emails.tenant-welcome', [
                'companyName' => $this->companyName,
                'tenantUrl' => $this->tenantUrl,
                'adminEmail' => $this->adminEmail,
            ]);
    }
}
