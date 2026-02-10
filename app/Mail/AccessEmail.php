<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccessEmail extends Mailable
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
        $mailConfigService = app(\App\Services\TenantMailConfigService::class);

        return $this->subject('Accede a ' . ($mailConfigService->getFromName()))
            ->from($mailConfigService->getFromAddress(), $mailConfigService->getFromName())
            ->view('emails.auth.access-html', [
                'magicLinkUrl' => $this->magicLinkUrl,
                'code' => $this->code,
                'expiresMinutes' => $this->expiresMinutes,
            ]);
    }
}
