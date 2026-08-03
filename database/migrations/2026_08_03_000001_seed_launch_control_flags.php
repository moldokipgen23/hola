<?php

use App\Services\LaunchControlService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(LaunchControlService::class)->syncDefinitions();
    }

    public function down(): void
    {
        // Launch settings are operational data and must not be destroyed on rollback.
    }
};
