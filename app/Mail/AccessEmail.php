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

        $companyName = $mailConfigService->getFromName();

        return $this->subject('Accede a ' . $companyName)
            ->from($mailConfigService->getFromAddress(), $companyName)
            ->view('emails.auth.access-html', [
                'magicLinkUrl' => $this->magicLinkUrl,
                'code' => $this->code,
                'expiresMinutes' => $this->expiresMinutes,
                'companyName' => $companyName,
            ]);
    }
}
