<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Re-encrypt any plaintext ai_agent api_key values so the model's
     * 'encrypted' cast can be used safely on a database that already has data.
     * Reads/writes via the query builder to bypass the model cast. Idempotent.
     */
    public function up(): void
    {
        $rows = DB::table('ai_agents')->whereNotNull('api_key')->get();

        foreach ($rows as $row) {
            $value = $row->api_key;

            // Already encrypted values (produced by Laravel's Crypt) are
            // base64-encoded JSON; plaintext keys are not. Try to decrypt —
            // if it fails, the value is plaintext and must be encrypted.
            $isEncrypted = true;
            try {
                Crypt::decryptString($value);
            } catch (\Throwable $e) {
                $isEncrypted = false;
            }

            if (! $isEncrypted) {
                DB::table('ai_agents')
                    ->where('id', $row->id)
                    ->update(['api_key' => Crypt::encryptString($value)]);
            }
        }
    }

    public function down(): void
    {
        // No-op: we do not decrypt existing data on rollback.
    }
};
