<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // All unclaimed businesses are AI-imported directory listings.
        // They must NOT have transactional modules enabled until a vendor
        // claims them and goes through the "What do you offer?" onboarding.
        DB::table('businesses')
            ->where('claim_status', 'unclaimed')
            ->whereNotNull('enabled_modules')
            ->update([
                'enabled_modules' => null,
                'enabled_experiences' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Irreversible — the original module assignments were set by the
        // normalize command and cannot be reliably restored.
    }
};
