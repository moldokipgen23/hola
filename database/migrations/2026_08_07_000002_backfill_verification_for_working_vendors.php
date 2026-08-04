<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Phase 6 lifecycle: verification now gates becoming a working vendor.
        // Businesses that are already claimed by an owner and have live modules
        // were approved under the old flow (claims auto-verified). Treat them as
        // verified so they keep operating; only unclaimed/listing-only or
        // genuinely pending businesses remain `pending`.
        DB::table('businesses')
            ->where('claim_status', 'claimed')
            ->where('verification_status', '!=', 'rejected')
            ->whereNotNull('enabled_modules')
            ->where('enabled_modules', '!=', '[]')
            ->where('enabled_modules', '!=', '{}')
            ->update([
                'verification_status' => 'verified',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Irreversible — prior verification status is not reliably recoverable.
    }
};
