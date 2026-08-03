<?php

namespace Tests\Feature;

use App\Models\AiAgent;
use App\Models\AiAgentTask;
use App\Services\AgentAssignmentService;
use App\Services\AgentSkillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentAutopilotAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_uses_active_skilled_agent_with_least_open_work(): void
    {
        AiAgent::query()->delete();
        $busy = $this->agent('Busy Scout', ['google_places_import']);
        $available = $this->agent('Available Scout', ['google_places_import']);
        $this->agent('Wrong Skill', ['quality_checker']);
        $this->agent('Paused Scout', ['google_places_import'], 'paused');

        AiAgentTask::create([
            'agent_id' => $busy->id, 'type' => 'google_places_import',
            'input' => ['query' => 'hotel'], 'status' => 'running',
        ]);

        $assigned = app(AgentAssignmentService::class)->forSkill('google_places_import');
        $this->assertSame($available->id, $assigned?->id);
        $this->assertNull(app(AgentAssignmentService::class)->forSkill('csv_importer'));
    }

    public function test_skill_runner_rejects_mismatched_assignment_and_marks_task_failed(): void
    {
        AiAgent::query()->delete();
        $agent = $this->agent('Quality Agent', ['quality_checker']);
        $task = AiAgentTask::create([
            'agent_id' => $agent->id, 'type' => 'description_writer',
            'input' => [], 'status' => 'pending',
        ]);

        try {
            app(AgentSkillService::class)->run($agent, $task);
            $this->fail('A task must not run on an agent without the required skill.');
        } catch (\RuntimeException) {
            $this->assertSame('failed', $task->fresh()->status);
            $this->assertStringContainsString('required skill', $task->fresh()->error);
        }
    }

    public function test_autopilot_dry_run_reports_assignment_and_rejects_unknown_skill(): void
    {
        AiAgent::query()->delete();
        $this->agent('Discovery Agent', ['google_places_import']);

        $this->artisan('agent:auto-run', ['--skill' => 'google_places_import', '--dry-run' => true])
            ->expectsOutputToContain('skill-aware assignment')
            ->expectsOutputToContain('Discovery Agent')
            ->assertSuccessful();

        $this->artisan('agent:auto-run', ['--skill' => 'not-a-skill', '--dry-run' => true])
            ->expectsOutputToContain('Unsupported Autopilot skill')
            ->assertFailed();
    }

    private function agent(string $name, array $skills, string $status = 'active'): AiAgent
    {
        return AiAgent::create([
            'name' => $name, 'role' => 'Test role', 'provider' => 'openrouter',
            'model' => 'test-model', 'skills' => $skills, 'status' => $status,
        ]);
    }
}
