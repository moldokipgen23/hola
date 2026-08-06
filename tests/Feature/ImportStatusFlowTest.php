<?php

namespace Tests\Feature;

use App\Models\AiAgent;
use App\Models\AiAgentTask;
use App\Models\Business;
use App\Models\Category;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Services\AgentSkillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportStatusFlowTest extends TestCase
{
    use RefreshDatabase;

    private function agent(array $skills = []): AiAgent
    {
        return AiAgent::create([
            'name' => 'Pipeline Agent',
            'role' => 'Importer',
            'provider' => 'openrouter',
            'model' => 'test-model',
            'api_key' => 'test-api-key',
            'skills' => $skills,
            'status' => 'active',
        ]);
    }

    private function task(AiAgent $agent, string $type, array $input = []): AiAgentTask
    {
        return AiAgentTask::create([
            'agent_id' => $agent->id,
            'type' => $type,
            'input' => $input,
            'status' => 'pending',
        ]);
    }

    private function batch(): ImportBatch
    {
        return ImportBatch::create([
            'source' => 'google_places',
            'name' => 'Test batch',
            'total' => 1,
            'pending' => 1,
            'status' => 'processing',
        ]);
    }

    private function item(ImportBatch $batch, array $data = [], string $status = 'pending'): ImportItem
    {
        return ImportItem::create([
            'batch_id' => $batch->id,
            'data' => array_merge(['name' => 'Test Cafe', 'address' => 'Main Road, Lamka'], $data),
            'status' => $status,
            'external_id' => 'place_test_'.uniqid(),
        ]);
    }

    public function test_pipeline_advances_item_through_statuses(): void
    {
        $category = Category::create(['name' => 'Pipeline Cafes', 'slug' => 'pipeline-cafes', 'module_type' => 'ordering', 'is_canonical' => true, 'is_active' => true]);
        $batch = $this->batch();
        $item = $this->item($batch);

        // STEP 1 — categorize: pending -> categorized
        Http::fake([
            '*' => Http::response(['choices' => [[
                'message' => ['content' => json_encode([[
                    'source' => 'import',
                    'source_id' => $item->id,
                    'category' => 'Pipeline Cafes',
                    'changed' => true,
                ]]), 'role' => 'assistant'],
            ]], 'usage' => ['total_tokens' => 100]]),
        ]);

        $agent = $this->agent(['auto_categorize', 'quality_checker']);
        $this->assertSame(1, ImportItem::pending()->count());

        app(AgentSkillService::class)->run($agent, $this->task($agent, 'auto_categorize', ['scope' => 'pending', 'max_results' => 30]));

        $item->refresh();
        $this->assertSame(ImportItem::STATUS_CATEGORIZED, $item->status);
        $this->assertSame('Pipeline Cafes', $item->data['category']);
        $this->assertSame(0, ImportItem::pending()->count());

        // STEP 2 — quality check: categorized -> review, confidence set
        $this->assertSame(1, ImportItem::categorized()->count());
        app(AgentSkillService::class)->run($agent, $this->task($agent, 'quality_checker', ['max_results' => 30]));

        $item->refresh();
        $this->assertSame(ImportItem::STATUS_REVIEW, $item->status);
        $this->assertNotSame(0, ImportItem::review()->count());
        $this->assertSame(0, ImportItem::categorized()->count());

        // The fully-processed item still shows up in the human review queue.
        $this->assertSame(1, ImportItem::inPipeline()->count());
    }

    public function test_quality_checker_leaves_uncategorized_items_pending(): void
    {
        $batch = $this->batch();
        $item = $this->item($batch);

        $agent = $this->agent(['quality_checker']);
        app(AgentSkillService::class)->run($agent, $this->task($agent, 'quality_checker', ['max_results' => 30]));

        $this->assertSame(ImportItem::STATUS_PENDING, $item->fresh()->status);
        $this->assertSame(0, ImportItem::review()->count());
    }

    public function test_duplicate_detector_marks_in_pipeline_duplicates_and_updates_batch(): void
    {
        $category = Category::create(['name' => 'Test Retail', 'slug' => 'test-retail-'.uniqid(), 'is_active' => true]);
        Business::create(['name' => 'Existing Shop', 'slug' => 'existing-shop-'.uniqid(), 'category_id' => $category->id, 'address' => 'Main Road', 'is_active' => true]);
        $batch = $this->batch();
        $item = $this->item($batch, ['name' => 'existing shop'], ImportItem::STATUS_REVIEW);

        $agent = $this->agent(['duplicate_detector']);
        app(AgentSkillService::class)->run($agent, $this->task($agent, 'duplicate_detector'));

        $this->assertSame(ImportItem::STATUS_DUPLICATE, $item->fresh()->status);
        $this->assertSame(1, $batch->fresh()->rejected);
        $this->assertSame(0, $batch->fresh()->pending);
    }

    public function test_description_writer_enriches_in_pipeline_items(): void
    {
        $batch = $this->batch();
        $item = $this->item($batch, [], ImportItem::STATUS_REVIEW);

        Http::fake([
            '*' => Http::response(['choices' => [[
                'message' => ['content' => 'A cozy local hangout.', 'role' => 'assistant'],
            ]]]),
        ]);

        $agent = $this->agent(['description_writer']);
        app(AgentSkillService::class)->run($agent, $this->task($agent, 'description_writer', ['max_results' => 10]));

        $item->refresh();
        $this->assertSame('A cozy local hangout.', $item->data['description']);
        $this->assertSame(ImportItem::STATUS_REVIEW, $item->status);
    }
}
