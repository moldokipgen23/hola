<?php

namespace Tests\Feature;

use App\Models\AiAgent;
use App\Models\AiAgentTask;
use App\Models\ImportItem;
use App\Services\AgentSkillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CsvImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_importer_handles_multiline_quoted_fields_and_ragged_rows(): void
    {
        $path = sys_get_temp_dir().'/import_test_'.uniqid().'.csv';
        File::put($path, "name,address,phone,description\n"
            ."Cafe A,Main Road,9000000001,\"Multi-line\n"
            ."description here\"\n"
            ."Short Shop\n"
            ."Ragged Shop,Street 2,,ExtraCol,TooManyCols\n");

        $agent = AiAgent::create([
            'name' => 'CSV Agent',
            'role' => 'Importer',
            'provider' => 'openrouter',
            'model' => 'test-model',
            'api_key' => 'test-key',
            'skills' => ['csv_importer'],
            'status' => 'active',
        ]);

        $task = AiAgentTask::create([
            'agent_id' => $agent->id,
            'type' => 'csv_importer',
            'input' => ['file_path' => $path],
            'status' => 'pending',
        ]);

        $result = app(AgentSkillService::class)->run($agent, $task);

        $this->assertSame(3, $result['imported']);

        $cafe = ImportItem::where('data->name', 'Cafe A')->first();
        $this->assertNotNull($cafe);
        $this->assertSame("Multi-line\ndescription here", $cafe->data['description']);

        $short = ImportItem::where('data->name', 'Short Shop')->first();
        $this->assertNotNull($short);
        $this->assertSame('', $short->data['address']);

        $ragged = ImportItem::where('data->name', 'Ragged Shop')->first();
        $this->assertNotNull($ragged);
        $this->assertSame('ExtraCol', $ragged->data['description']);

        File::delete($path);
    }

    public function test_csv_importer_skips_rows_without_a_name(): void
    {
        $path = sys_get_temp_dir().'/import_test_'.uniqid().'.csv';
        File::put($path, "name,address,phone\n,,9000000002\nCafe B,Main Road,9000000003\n");

        $agent = AiAgent::create([
            'name' => 'CSV Agent',
            'role' => 'Importer',
            'provider' => 'openrouter',
            'model' => 'test-model',
            'api_key' => 'test-key',
            'skills' => ['csv_importer'],
            'status' => 'active',
        ]);

        $task = AiAgentTask::create([
            'agent_id' => $agent->id,
            'type' => 'csv_importer',
            'input' => ['file_path' => $path],
            'status' => 'pending',
        ]);

        $result = app(AgentSkillService::class)->run($agent, $task);

        $this->assertSame(1, $result['imported']);
        $this->assertNull(ImportItem::where('data->phone', '9000000002')->first());
        $this->assertNotNull(ImportItem::where('data->name', 'Cafe B')->first());

        File::delete($path);
    }
}
