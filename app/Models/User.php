<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements \Illuminate\Contracts\Auth\MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, \Illuminate\Auth\MustVerifyEmail;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'google_id',
        'avatar',
        'role',
        'is_active',
        'banned_at',
        'ban_reason',
        'last_login_at',
        'login_count',
        'created_by_admin',
        'email_verified_at',
        'phone_verified_at',
        'otp',
        'otp_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'otp',
        'otp_expires_at',
        'google_id',
        'banned_at',
        'ban_reason',
        'login_count',
        'created_by_admin',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'banned_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function ownedBusinesses(): HasMany
    {
        return $this->hasMany(Business::class, 'created_by');
    }

    public function savedListings(): HasMany
    {
        return $this->hasMany(SavedListing::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function claimRequests(): HasMany
    {
        return $this->hasMany(ClaimRequest::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(\App\Models\Notification::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(\App\Models\Conversation::class, 'user_id');
    }

    public function ownedConversations(): HasMany
    {
        return $this->hasMany(\App\Models\Conversation::class, 'business_owner_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_admin');
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['super_admin', 'admin', 'moderator']);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isModerator(): bool
    {
        return $this->role === 'moderator';
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    public function isActive(): bool
    {
        return $this->is_active && !$this->isBanned();
    }

    public function ban(string $reason = null): void
    {
        $this->update([
            'banned_at' => now(),
            'ban_reason' => $reason,
            'is_active' => false,
        ]);
    }

    public function unban(): void
    {
        $this->update([
            'banned_at' => null,
            'ban_reason' => null,
            'is_active' => true,
        ]);
    }

    public function recordLogin(): void
    {
        $this->update([
            'last_login_at' => now(),
            'login_count' => $this->login_count + 1,
        ]);
    }
}
