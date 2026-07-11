<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimVerification extends Model
{
    protected $fillable = [
        'business_id',
        'phone',
        'email',
        'otp',
        'channel',
        'verified',
        'expires_at',
    ];

    protected $casts = [
        'verified' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function verify(string $inputOtp): bool
    {
        if ($this->isExpired() || $this->verified) {
            return false;
        }

        if (hash_equals($this->otp, $inputOtp)) {
            $this->update(['verified' => true]);
            return true;
        }

        return false;
    }

    public static function generateOtp(): string
    {
        return str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }
}
