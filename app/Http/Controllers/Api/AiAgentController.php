<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiAgent;
use App\Models\AiAgentTask;
use App\Models\ImportItem;
use Illuminate\Http\Request;

class AiAgentController extends Controller
{
    public function index()
    {
        $agents = AiAgent::withCount(['tasks', 'importBatches'])
            ->latest()
            ->get();

        return response()->json(['agents' => $agents]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'avatar' => 'nullable|string|max:10',
            'role' => 'required|string|max:255',
            'description' => 'nullable|string',
            'provider' => 'required|in:openrouter,openai,deepseek,anthropic',
            'api_key' => 'nullable|string|max:255',
            'model' => 'required|string|max:255',
            'system_prompt' => 'nullable|string',
            'skills' => 'required|array|min:1',
            'skills.*' => 'string|in:google_places_import,serpapi_business_search,ai_business_scraper,auto_categorize,duplicate_detector,description_writer,quality_checker,csv_importer',
            'config' => 'nullable|array',
        ]);

        $validated['config'] = $validated['config'] ?? [];

        $agent = AiAgent::create($validated);

        return response()->json(['agent' => $agent], 201);
    }

    public function show($id)
    {
        $agent = AiAgent::withCount(['tasks', 'importBatches'])->findOrFail($id);

        $recentTasks = $agent->tasks()->latest()->take(10)->get();

        return response()->json([
            'agent' => $agent,
            'recent_tasks' => $recentTasks,
        ]);
    }

    public function update(Request $request, $id)
    {
        $agent = AiAgent::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'avatar' => 'nullable|string|max:10',
            'role' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'provider' => 'sometimes|in:openrouter,openai,deepseek,anthropic',
            'api_key' => 'nullable|string|max:255',
            'model' => 'sometimes|string|max:255',
            'system_prompt' => 'nullable|string',
            'skills' => 'sometimes|array|min:1',
            'skills.*' => 'string|in:google_places_import,serpapi_business_search,ai_business_scraper,auto_categorize,duplicate_detector,description_writer,quality_checker,csv_importer',
            'status' => 'sometimes|in:active,paused',
            'config' => 'nullable|array',
        ]);

        $agent->update($validated);

        return response()->json(['agent' => $agent]);
    }

    public function destroy($id)
    {
        $agent = AiAgent::findOrFail($id);
        $agent->delete();

        return response()->json(['message' => 'Agent deleted.']);
    }

    public function runTask(Request $request, $id)
    {
        $agent = AiAgent::findOrFail($id);

        if ($agent->status !== 'active') {
            return response()->json(['error' => 'Agent is paused.'], 422);
        }

        $request->validate([
            'skill' => 'required|string|in:' . implode(',', $agent->skills),
        ]);

        $skill = $request->skill;

        // Create task record
        $task = AiAgentTask::create([
            'agent_id' => $agent->id,
            'type' => $skill,
            'input' => $request->except(['skill']),
            'status' => 'pending',
        ]);

        // Run skill via service
        $service = app(\App\Services\AgentSkillService::class);
        $result = $service->run($agent, $task);

        return response()->json([
            'task' => $task->fresh(),
            'result' => $result,
        ]);
    }

    public function tasks($id)
    {
        $agent = AiAgent::findOrFail($id);
        $tasks = $agent->tasks()->latest()->paginate(20);

        return response()->json(['tasks' => $tasks]);
    }

    public function stats()
    {
        $stats = [
            'total_agents' => AiAgent::count(),
            'active_agents' => AiAgent::active()->count(),
            'total_tasks' => AiAgentTask::count(),
            'completed_tasks' => AiAgentTask::completed()->count(),
            'failed_tasks' => AiAgentTask::failed()->count(),
            'total_imports' => ImportItem::count(),
            'pending_review' => ImportItem::pending()->count(),
            'total_cost' => AiAgent::sum('total_cost'),
        ];

        return response()->json(['stats' => $stats]);
    }
}
