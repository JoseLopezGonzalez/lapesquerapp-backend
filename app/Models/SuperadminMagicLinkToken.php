<?php

namespace App\Models;

use App\Casts\DateTimeUtcCast;
use Illuminate\Database\Eloquent\Model;

class SuperadminMagicLinkToken extends Model
{
    protected $connection = 'mysql';

    protected $table = 'superadmin_magic_link_tokens';

    public const TYPE_MAGIC_LINK = 'magic_link';
    public const TYPE_OTP = 'otp';

    protected $fillable = [
        'email',
        'token',
        'type',
        'otp_code',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => DateTimeUtcCast::class,
        'used_at' => DateTimeUtcCast::class,
    ];

    public function scopeValid($query)
    {
        return $query->whereNull('used_at')->where('expires_at', '>', now('UTC'));
    }

    public function scopeMagicLink($query)
    {
        return $query->where('type', self::TYPE_MAGIC_LINK);
    }

    public function scopeOtp($query)
    {
        return $query->where('type', self::TYPE_OTP);
    }

    public function markAsUsed(): void
    {
        $this->update(['used_at' => now('UTC')]);
    }

    public function isValid(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
