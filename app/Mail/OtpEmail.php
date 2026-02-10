<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $email,
        public string $code,
        public int $expiresMinutes = 10
    ) {}

    public function build()
    {
        $mailConfigService = app(\App\Services\TenantMailConfigService::class);

        return $this->subject('Tu código de acceso')
            ->from($mailConfigService->getFromAddress(), $mailConfigService->getFromName())
            ->markdown('emails.auth.otp', [
                'code' => $this->code,
                'expiresMinutes' => $this->expiresMinutes,
            ]);
    }
}
