<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $agent = DB::table('ai_agents')->where('id', 1)->first();
        if (! $agent) {
            return;
        }

        $prompt = $agent->system_prompt;

        // Replace TIER 3 section with public businesses
        $oldTier3 = <<<'OLD'
**TIER 3 — LOW PRIORITY (Search occasionally):**
- Churches & Religious Places
- Community Halls
- Other businesses
OLD;

        $newTier3 = <<<'NEW'
**TIER 3 — LOW PRIORITY (Search occasionally):**
- Banks & ATMs
- Post Offices
- Police Stations
- Fire Stations
- Community Halls
- Churches, Temples, Mosques
- Government Offices
- Petrol Pumps
- Diagnostic Labs
- Blood Banks
- Water Supply / Electricity Offices
- Telecom Offices
- Other public utility businesses
NEW;

        $newPrompt = str_replace($oldTier3, $newTier3, $prompt);

        // Also update SEARCH STRATEGY
        $oldStrategy = <<<'OLD2'
- Search TIER 3 categories every 4th run
OLD2;

        $newStrategy = <<<'NEW2'
- Search TIER 3 categories every 5th run (public utilities, banks, ATMs, etc.)
NEW2;

        $newPrompt = str_replace($oldStrategy, $newStrategy, $newPrompt);

        DB::table('ai_agents')->where('id', 1)->update(['system_prompt' => $newPrompt]);
    }

    public function down(): void
    {
        //
    }
};
