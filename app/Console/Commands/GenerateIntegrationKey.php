<?php

namespace App\Console\Commands;

use App\Models\IntegrationApiKey;
use Illuminate\Console\Command;

class GenerateIntegrationKey extends Command
{
    protected $signature = 'integration:generate-key
        {--name= : Human-readable name for the key}
        {--scope=* : Scopes to grant (default: all)}
        {--tenant-type= : Tenant type (hola, ai_agent, etc.)}
        {--tenant-id= : Tenant ID}
        {--expires= : Expiration date (Y-m-d H:i:s)}';

    protected $description = 'Generate a new Ehlom Integration API key';

    public function handle(): int
    {
        $name = $this->option('name') ?? $this->ask('Key name', 'Default Integration Key');
        $scopes = $this->option('scope') ?: ['*'];

        $tenant = null;
        if ($type = $this->option('tenant-type') ?: $this->anticipate('Tenant type (optional)', ['hola', 'ai_agent', 'restaurant', 'school', 'shop'])) {
            $id = $this->option('tenant-id') ?? $this->ask('Tenant ID');
            $tenant = ['type' => $type, 'id' => (int) $id];
        }

        $result = IntegrationApiKey::generateKey(
            $name,
            $scopes,
            $tenant,
            null,
            null,
            $this->option('expires'),
        );

        $this->components->info('API key created successfully!');
        $this->newLine();
        $this->components->twoColumnDetail('Key ID', (string) $result['key']->id);
        $this->components->twoColumnDetail('Name', $result['key']->name);
        $this->components->twoColumnDetail('Prefix', $result['key']->key_prefix);
        $this->components->twoColumnDetail('Scopes', implode(', ', $result['key']->scopes));
        $this->newLine();
        $this->warn(' Store this key securely — it will not be shown again:');
        $this->line(' ───────────────────────────────────────────────────────────');
        $this->line('  ' . $result['raw_key']);
        $this->line(' ───────────────────────────────────────────────────────────');

        return self::SUCCESS;
    }
}
