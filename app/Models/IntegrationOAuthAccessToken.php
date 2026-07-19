<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationOAuthAccessToken extends Model
{
    protected $table = 'integration_oauth_access_tokens';

    protected $fillable = [
        'id',
        'client_id',
        'user_id',
        'scopes',
        'is_revoked',
        'expires_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'is_revoked' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function client()
    {
        return $this->belongsTo(IntegrationOAuthClient::class, 'client_id');
    }

    public function scopeValid($query)
    {
        return $query->where('is_revoked', false)
            ->where('expires_at', '>', now());
    }

    public function hasScope(string $scope): bool
    {
        $scopes = $this->scopes ?? [];
        return in_array('*', $scopes) || in_array($scope, $scopes);
    }
}
