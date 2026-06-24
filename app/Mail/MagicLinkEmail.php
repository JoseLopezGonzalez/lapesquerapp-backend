<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MagicLinkEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $email,
        public string $magicLinkUrl,
        public int $expiresMinutes = 10
    ) {}

    public function build()
    {
        $mailConfigService = app(\App\Services\TenantMailConfigService::class);

        $companyName = $mailConfigService->getFromName();

        return $this->subject('Inicia sesión en ' . $companyName)
            ->from($mailConfigService->getFromAddress(), $companyName)
            ->view('emails.auth.magic-link', [
                'magicLinkUrl' => $this->magicLinkUrl,
                'expiresMinutes' => $this->expiresMinutes,
                'companyName' => $companyName,
            ]);
    }
}
