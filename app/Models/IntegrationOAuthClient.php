<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class IntegrationOAuthClient extends Model
{
    protected $table = 'integration_oauth_clients';

    protected $fillable = [
        'tenant_type',
        'tenant_id',
        'name',
        'client_id',
        'client_secret',
        'redirect_uris',
        'grants',
        'scopes',
        'is_confidential',
    ];

    protected $hidden = ['client_secret'];

    protected function casts(): array
    {
        return [
            'redirect_uris' => 'array',
            'grants' => 'array',
            'scopes' => 'array',
            'is_confidential' => 'boolean',
        ];
    }

    public function accessTokens()
    {
        return $this->hasMany(IntegrationOAuthAccessToken::class, 'client_id');
    }

    public function authorizationCodes()
    {
        return $this->hasMany(IntegrationOAuthAuthorizationCode::class, 'client_id');
    }

    public function scopeForTenant($query, string $tenantType, int $tenantId)
    {
        return $query->where('tenant_type', $tenantType)->where('tenant_id', $tenantId);
    }

    public static function generateCredentials(string $name, array $grants = ['client_credentials'], array $scopes = ['*']): array
    {
        $clientId = Str::random(24);
        $clientSecret = Str::random(48);

        return [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ];
    }

    public function verifySecret(string $secret): bool
    {
        return $this->client_secret && hash_equals($this->client_secret, $secret);
    }
}
