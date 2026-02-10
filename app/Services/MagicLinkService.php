<?php

namespace App\Services;

use App\Mail\AccessEmail;
use App\Models\MagicLinkToken;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class MagicLinkService
{
    public function __construct(
        protected TenantMailConfigService $mailConfig
    ) {}

    protected function expiresMinutes(): int
    {
        return (int) config('magic_link.expires_minutes', 10);
    }

    /**
     * Envía un único email con magic link + código OTP (flujo tipo Claude: un clic "Acceder").
     * Crea un token de magic link y un token OTP; el usuario puede usar el enlace o el código.
     * Returns true si se envió, false si no (ej. FRONTEND_URL no configurada).
     */
    public function sendAccessEmailToUser(User $user): bool
    {
        $frontendUrl = magicLinkFrontendUrl();
        if (empty($frontendUrl)) {
            return false;
        }

        $token = Str::random(64);
        $code = (string) random_int(100000, 999999);
        $expiresAt = now()->addMinutes($this->expiresMinutes());

        MagicLinkToken::create([
            'email' => $user->email,
            'token' => hash('sha256', $token),
            'type' => MagicLinkToken::TYPE_MAGIC_LINK,
            'expires_at' => $expiresAt,
        ]);

        MagicLinkToken::create([
            'email' => $user->email,
            'token' => hash('sha256', $code . $user->email . now()->timestamp),
            'type' => MagicLinkToken::TYPE_OTP,
            'otp_code' => $code,
            'expires_at' => $expiresAt,
        ]);

        $magicLinkUrl = $frontendUrl . '/auth/verify?token=' . $token;

        $this->mailConfig->configureTenantMailer();
        Mail::to($user->email)->send(
            new AccessEmail($user->email, $magicLinkUrl, $code, $this->expiresMinutes())
        );

        return true;
    }

    /**
     * Generate a magic link for the user and send it by email.
     * Ahora envía el mismo email unificado (link + OTP) para consistencia.
     * Returns a non-null value on success (for backward compatibility), null on failure.
     */
    public function sendMagicLinkToUser(User $user): ?string
    {
        return $this->sendAccessEmailToUser($user) ? 'sent' : null;
    }
}
