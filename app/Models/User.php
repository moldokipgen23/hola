<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'google_id',
        'avatar',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
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
}
