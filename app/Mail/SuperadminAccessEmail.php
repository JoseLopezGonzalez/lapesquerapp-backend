<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SuperadminAccessEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $email,
        public string $magicLinkUrl,
        public string $code,
        public int $expiresMinutes = 10
    ) {}

    public function build()
    {
        return $this->subject('Accede al Panel de Administración — PesquerApp')
            ->from(
                config('mail.from.address', 'noreply@lapesquerapp.es'),
                config('mail.from.name', 'PesquerApp')
            )
            ->view('emails.auth.access-html', [
                'magicLinkUrl' => $this->magicLinkUrl,
                'code' => $this->code,
                'expiresMinutes' => $this->expiresMinutes,
            ]);
    }
}
