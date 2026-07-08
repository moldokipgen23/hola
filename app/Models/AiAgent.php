<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiAgent extends Model
{
    protected $fillable = [
        'name',
        'avatar',
        'role',
        'description',
        'provider',
        'api_key',
        'model',
        'system_prompt',
        'skills',
        'config',
        'status',
        'tasks_completed',
        'tasks_failed',
        'total_cost',
        'last_active_at',
    ];

    protected $hidden = [
        'api_key',
    ];

    protected $casts = [
        'skills' => 'array',
        'config' => 'array',
        'api_key' => 'encrypted',
        'total_cost' => 'decimal:4',
        'last_active_at' => 'datetime',
    ];

    public function tasks(): HasMany
    {
        return $this->hasMany(AiAgentTask::class, 'agent_id');
    }

    public function importBatches(): HasMany
    {
        return $this->hasMany(ImportBatch::class, 'agent_id');
    }

    public function hasSkill(string $skill): bool
    {
        return in_array($skill, $this->skills ?? []);
    }

    public function getApiKeyDecrypted(): ?string
    {
        if ($this->api_key) {
            return $this->api_key;
        }

        $settingKey = 'api_key_' . ($this->provider ?? 'openrouter');
        $settingValue = \App\Models\Setting::get($settingKey);

        if ($settingValue) {
            return $settingValue;
        }

        return match ($this->provider) {
            'openai' => config('services.openai.api_key'),
            'deepseek' => config('services.deepseek.api_key'),
            'anthropic' => config('services.anthropic.api_key'),
            default => config('services.openrouter.api_key'),
        };
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
