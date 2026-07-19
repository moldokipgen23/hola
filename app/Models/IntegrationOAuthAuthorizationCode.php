<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationOAuthAuthorizationCode extends Model
{
    protected $table = 'integration_oauth_authorization_codes';

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
}
