<?php

namespace App\Services;

use App\Mail\MagicLinkEmail;
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
     * Generate a magic link for the user and send it by email.
     * Returns the plain token on success (for optional logging), or null on failure.
     */
    public function sendMagicLinkToUser(User $user): ?string
    {
        $token = Str::random(64);
        $expiresAt = now()->addMinutes($this->expiresMinutes());

        MagicLinkToken::create([
            'email' => $user->email,
            'token' => hash('sha256', $token),
            'type' => MagicLinkToken::TYPE_MAGIC_LINK,
            'expires_at' => $expiresAt,
        ]);

        $frontendUrl = magicLinkFrontendUrl();
        if (empty($frontendUrl)) {
            return null;
        }

        $magicLinkUrl = $frontendUrl . '/auth/verify?token=' . $token;

        $this->mailConfig->configureTenantMailer();
        Mail::to($user->email)->send(
            new MagicLinkEmail($user->email, $magicLinkUrl, $this->expiresMinutes())
        );

        return $token;
    }
}
