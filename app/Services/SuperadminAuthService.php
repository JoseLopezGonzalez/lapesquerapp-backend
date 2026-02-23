<?php

namespace App\Services;

use App\Mail\AccessEmail;
use App\Models\SuperadminMagicLinkToken;
use App\Models\SuperadminUser;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SuperadminAuthService
{
    protected function expiresMinutes(): int
    {
        return (int) config('magic_link.expires_minutes', 10);
    }

    /**
     * Send unified access email (magic link + OTP) to a superadmin user.
     * Uses the global mailer config (no tenant mail config).
     */
    public function sendAccessEmail(SuperadminUser $user): bool
    {
        $frontendUrl = rtrim(config('superadmin.frontend_url', 'https://admin.lapesquerapp.es'), '/');
        if (empty($frontendUrl)) {
            return false;
        }

        $token = Str::random(64);
        $code = (string) random_int(100000, 999999);
        $expiresAt = now('UTC')->addMinutes($this->expiresMinutes());

        SuperadminMagicLinkToken::create([
            'email' => $user->email,
            'token' => hash('sha256', $token),
            'type' => SuperadminMagicLinkToken::TYPE_MAGIC_LINK,
            'expires_at' => $expiresAt,
        ]);

        SuperadminMagicLinkToken::create([
            'email' => $user->email,
            'token' => hash('sha256', $code . $user->email . now('UTC')->timestamp),
            'type' => SuperadminMagicLinkToken::TYPE_OTP,
            'otp_code' => $code,
            'expires_at' => $expiresAt,
        ]);

        $magicLinkUrl = $frontendUrl . '/auth/verify?token=' . $token;

        Mail::to($user->email)->send(
            new \App\Mail\SuperadminAccessEmail($user->email, $magicLinkUrl, $code, $this->expiresMinutes())
        );

        return true;
    }
}
