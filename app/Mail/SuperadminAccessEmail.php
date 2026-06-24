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
        $appName = config('app.name', 'PesquerApp');

        return $this->subject('Accede al Panel de Administración — ' . $appName)
            ->from(
                config('mail.from.address', 'noreply@lapesquerapp.es'),
                $appName
            )
            ->view('emails.auth.access-html', [
                'magicLinkUrl' => $this->magicLinkUrl,
                'code' => $this->code,
                'expiresMinutes' => $this->expiresMinutes,
                'companyName' => $appName,
            ]);
    }
}
