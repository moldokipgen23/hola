<?php

namespace App\Services;

use App\Models\AiAgent;
use App\Models\AiAgentTask;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\Business;
use App\Models\Category;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AgentSkillService
{
    public function run(AiAgent $agent, AiAgentTask $task): array
    {
        $task->update(['status' => 'running']);
        $startTime = microtime(true);

        try {
            $result = match ($task->type) {
                'google_places_import' => $this->googlePlacesImport($agent, $task),
                'ai_business_scraper' => $this->aiBusinessScraper($agent, $task),
                'auto_categorize' => $this->autoCategorize($agent, $task),
                'duplicate_detector' => $this->duplicateDetector($agent, $task),
                'description_writer' => $this->descriptionWriter($agent, $task),
                'quality_checker' => $this->qualityChecker($agent, $task),
                'csv_importer' => $this->csvImporter($agent, $task),
                default => throw new \Exception("Unknown skill: {$task->type}"),
            };

            $duration = (int) ((microtime(true) - $startTime) * 1000);

            $task->update([
                'status' => 'completed',
                'output' => $result,
                'result_count' => $result['count'] ?? 0,
                'imported_count' => $result['imported'] ?? 0,
                'cost' => $result['cost'] ?? 0,
                'duration_ms' => $duration,
            ]);

            $agent->increment('tasks_completed');
            $agent->increment('total_cost', $result['cost'] ?? 0);
            $agent->update(['last_active_at' => now()]);

            return $result;

        } catch (\Exception $e) {
            $duration = (int) ((microtime(true) - $startTime) * 1000);

            $task->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'duration_ms' => $duration,
            ]);

            $agent->increment('tasks_failed');
            $agent->update(['last_active_at' => now()]);

            throw $e;
        }
    }

    private function getApiConfig(AiAgent $agent): array
    {
        $provider = $agent->provider ?? 'openrouter';
        $agentKey = $agent->getApiKeyDecrypted();

        $configs = [
            'openrouter' => [
                'endpoint' => 'https://openrouter.ai/api/v1/chat/completions',
                'api_key' => $agentKey ?: config('services.openrouter.api_key'),
                'model' => $agent->model ?? 'deepseek/deepseek-chat',
            ],
            'deepseek' => [
                'endpoint' => 'https://api.deepseek.com/v1/chat/completions',
                'api_key' => $agentKey ?: config('services.deepseek.api_key'),
                'model' => $agent->model ?? 'deepseek-chat',
            ],
            'openai' => [
                'endpoint' => 'https://api.openai.com/v1/chat/completions',
                'api_key' => $agentKey ?: config('services.openai.api_key'),
                'model' => $agent->model ?? 'gpt-4o',
            ],
            'anthropic' => [
                'endpoint' => 'https://api.anthropic.com/v1/messages',
                'api_key' => $agentKey ?: config('services.anthropic.api_key'),
                'model' => $agent->model ?? 'claude-3-5-sonnet-20241022',
            ],
        ];

        return $configs[$provider] ?? $configs['openrouter'];
    }

    private function googlePlacesImport(AiAgent $agent, AiAgentTask $task): array
    {
        $input = $task->input;
        $apiKey = $agent->getApiKeyDecrypted() ?? config('services.google.places_api_key');

        if (!$apiKey) {
            throw new \Exception('Google Places API key not configured.');
        }

        $query = $input['query'] ?? '';
        $lat = $input['latitude'] ?? 24.4871;
        $lng = $input['longitude'] ?? 93.6998;
        $maxResults = min($input['max_results'] ?? 20, 60);

        // Nearby search
        $response = Http::get('https://maps.googleapis.com/maps/api/place/nearbysearch/json', [
            'location' => "{$lat},{$lng}",
            'radius' => $input['radius'] ?? 5000,
            'keyword' => $query,
            'key' => $apiKey,
        ]);

        if ($response->failed()) {
            throw new \Exception('Google Places API request failed.');
        }

        $data = $response->json();
        $places = array_slice($data['results'] ?? [], 0, $maxResults);

        $batch = ImportBatch::create([
            'agent_id' => $agent->id,
            'source' => 'google_places',
            'name' => "Google: {$query} ({$lat},{$lng})",
            'total' => count($places),
            'status' => 'processing',
        ]);

        $imported = 0;
        $skipped = 0;
        $categories = Category::pluck('id', 'name')->toArray();

        // Pre-load existing businesses for duplicate detection
        $existingPlaceIds = Business::whereNotNull('external_id')->pluck('external_id')->toArray();
        $existingNames = Business::pluck('name')->map(fn($n) => Str::lower($n))->toArray();

        foreach ($places as $place) {
            $placeId = $place['place_id'] ?? null;
            $name = $place['name'] ?? '';
            $address = $place['vicinity'] ?? '';

            // DUPLICATE CHECK 1: Skip if google_place_id already exists
            if ($placeId && in_array($placeId, $existingPlaceIds)) {
                $skipped++;
                continue;
            }

            // DUPLICATE CHECK 2: Skip if name+address combination already exists
            $normalizedName = Str::lower(trim($name));
            $normalizedAddress = Str::lower(trim($address));
            if (in_array($normalizedName, $existingNames)) {
                // Double-check with address to allow same name in different locations
                $existingWithSameName = Business::whereRaw('LOWER(name) = ?', [$normalizedName])->first();
                if ($existingWithSameName && Str::contains(Str::lower($existingWithSameName->address), substr($normalizedAddress, 0, 10))) {
                    $skipped++;
                    continue;
                }
            }

            // QUALITY CHECK: Skip businesses without useful data
            $phone = $place['phone_number'] ?? null;
            $website = $place['website'] ?? null;
            $rating = $place['rating'] ?? null;
            $totalRatings = $place['user_ratings_total'] ?? 0;

            // Must have at least a phone or website or decent rating
            if (!$phone && !$website && ($rating === null || $totalRatings < 3)) {
                $skipped++;
                continue;
            }

            // Must have a valid address
            if (strlen($address) < 5) {
                $skipped++;
                continue;
            }

            // Capture photo references
            $photos = [];
            if (!empty($place['photos'])) {
                foreach (array_slice($place['photos'], 0, 5) as $photo) {
                    if (!empty($photo['photo_reference'])) {
                        $photos[] = "https://maps.googleapis.com/maps/api/place/photo?photoreference={$photo['photo_reference']}&maxwidth=800&key={$apiKey}";
                    }
                }
            }

            $placeData = [
                'name' => $name,
                'address' => $address,
                'latitude' => $place['geometry']['location']['lat'] ?? null,
                'longitude' => $place['geometry']['location']['lng'] ?? null,
                'phone' => $phone,
                'website' => $website,
                'rating' => $rating,
                'total_ratings' => $totalRatings,
                'types' => $place['types'] ?? [],
                'is_open' => $place['opening_hours']['open_now'] ?? null,
                'google_place_id' => $placeId,
                'photos' => $photos,
                'photo_references' => array_map(fn($p) => $p['photo_reference'] ?? null, $place['photos'] ?? []),
            ];

            // Auto-match category
            $matchedCategory = $this->matchCategory($place['types'] ?? [], array_keys($categories));

            // Calculate confidence based on data quality
            $confidence = 0.5;
            if ($phone) $confidence += 0.1;
            if ($website) $confidence += 0.1;
            if ($rating && $rating >= 4.0) $confidence += 0.1;
            if ($totalRatings >= 10) $confidence += 0.1;
            if (count($photos) > 0) $confidence += 0.1;
            $confidence = min($confidence, 1.0);

            ImportItem::create([
                'batch_id' => $batch->id,
                'data' => $placeData,
                'external_id' => $placeId,
                'confidence' => $confidence,
            ]);

            // Track for future duplicate detection
            if ($placeId) $existingPlaceIds[] = $placeId;
            $existingNames[] = $normalizedName;
            $imported++;
        }

        $batch->update([
            'status' => 'completed',
            'pending' => $imported,
        ]);

        return [
            'count' => count($places),
            'imported' => $imported,
            'skipped' => $skipped,
            'batch_id' => $batch->id,
            'cost' => 0.0,
        ];
    }

    private function aiBusinessScraper(AiAgent $agent, AiAgentTask $task): array
    {
        $input = $task->input;
        $api = $this->getApiConfig($agent);

        if (!$api['api_key']) {
            throw new \Exception("API key not configured. Set the API key for this agent or add it to your .env for provider: {$agent->provider}.");
        }

        $area = $input['area'] ?? 'Lamka, Churachandpur';
        $category = $input['category'] ?? 'all businesses';
        $maxResults = min($input['max_results'] ?? 30, 50);

        $categories = Category::pluck('name')->toArray();
        $categoryList = implode(', ', array_slice($categories, 0, 20));

        $prompt = <<<EOT
List real, existing {$category} businesses in {$area}, Churachandpur district, Manipur, India.

Return ONLY a JSON array. Each item must have:
- name: business name
- address: full address
- phone: phone number (if known, otherwise null)
- description: brief description of what they do
- website: website URL (if known, otherwise null)
- category: best matching category from this list: {$categoryList}

Rules:
- Only include REAL businesses that actually exist
- Do NOT make up or hallucinate businesses
- If you don't know many businesses, return fewer results
- Return maximum {$maxResults} businesses
- Return ONLY the JSON array, no other text
EOT;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $api['api_key'],
            'Content-Type' => 'application/json',
        ])->timeout(60)->post($api['endpoint'], [
            'model' => $api['model'],
            'messages' => [
                ['role' => 'system', 'content' => $agent->system_prompt ?? 'You are a business research assistant. Return only valid JSON.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.3,
            'max_tokens' => 4000,
        ]);

        if ($response->failed()) {
            $error = $response->json();
            $message = $error['error']['message'] ?? $response->body();
            if ($response->status() === 401) {
                $message = "Invalid or missing API key for {$agent->provider}. Add the key to this agent or set it in your .env.";
            }
            throw new \Exception('AI API request failed: ' . $message);
        }

        $result = $response->json();
        $content = $result['choices'][0]['message']['content'] ?? '';

        // Parse JSON from response
        $businesses = $this->parseJsonFromResponse($content);

        if (!is_array($businesses)) {
            throw new \Exception('AI returned invalid JSON. Response: ' . substr($content, 0, 500));
        }

        $batch = ImportBatch::create([
            'agent_id' => $agent->id,
            'source' => 'ai_scrape',
            'name' => "AI: {$category} in {$area}",
            'total' => count($businesses),
            'status' => 'processing',
        ]);

        $imported = 0;
        $skipped = 0;

        // Pre-load existing businesses for duplicate detection
        $existingNames = Business::pluck('name')->map(fn($n) => Str::lower($n))->toArray();
        $existingPhones = Business::whereNotNull('phone')->pluck('phone')->map(fn($p) => Str::replace([' ', '-', '(', ')'], '', $p))->toArray();

        foreach ($businesses as $biz) {
            $name = $biz['name'] ?? null;
            $phone = $biz['phone'] ?? null;
            $address = $biz['address'] ?? null;

            if (empty($name)) {
                $skipped++;
                continue;
            }

            // DUPLICATE CHECK 1: Skip if name already exists
            $normalizedName = Str::lower(trim($name));
            if (in_array($normalizedName, $existingNames)) {
                $skipped++;
                continue;
            }

            // DUPLICATE CHECK 2: Skip if phone number already exists (normalized)
            if ($phone) {
                $normalizedPhone = Str::replace([' ', '-', '(', ')'], '', $phone);
                if (in_array($normalizedPhone, $existingPhones)) {
                    $skipped++;
                    continue;
                }
            }

            // QUALITY CHECK: Must have at least a phone or address
            if (!$phone && !$address) {
                $skipped++;
                continue;
            }

            $itemData = [
                'name' => $name,
                'address' => $address,
                'phone' => $phone,
                'description' => $biz['description'] ?? null,
                'website' => $biz['website'] ?? null,
                'category' => $biz['category'] ?? null,
            ];

            // Calculate confidence based on data quality
            $confidence = 0.5;
            if ($phone) $confidence += 0.15;
            if ($address) $confidence += 0.1;
            if (!empty($itemData['website'])) $confidence += 0.1;
            if (!empty($itemData['description'])) $confidence += 0.1;
            $confidence = min($confidence, 1.0);

            ImportItem::create([
                'batch_id' => $batch->id,
                'data' => $itemData,
                'confidence' => $confidence,
            ]);

            // Track for future duplicate detection
            $existingNames[] = $normalizedName;
            if ($phone) $existingPhones[] = Str::replace([' ', '-', '(', ')'], '', $phone);
            $imported++;
        }

        $batch->update([
            'status' => 'completed',
            'pending' => $imported,
        ]);

        $cost = $result['usage']['total_tokens'] ? ($result['usage']['total_tokens'] * 0.00000014) : 0;

        return [
            'count' => count($businesses),
            'imported' => $imported,
            'skipped' => $skipped,
            'batch_id' => $batch->id,
            'cost' => round($cost, 4),
        ];
    }

    private function autoCategorize(AiAgent $agent, AiAgentTask $task): array
    {
        $input = $task->input;
        $batchId = $input['batch_id'] ?? null;
        $maxResults = $input['max_results'] ?? 50;

        if (!$batchId) {
            throw new \Exception('batch_id is required.');
        }

        $items = ImportItem::where('batch_id', $batchId)
            ->whereNull('business_id')
            ->limit($maxResults)
            ->get();

        $categories = Category::all()->keyBy('name');
        $matched = 0;

        foreach ($items as $item) {
            $data = $item->data;
            $categoryName = $data['category'] ?? null;

            if ($categoryName && $categories->has($categoryName)) {
                $item->update(['confidence' => min($item->confidence + 0.2, 1.0)]);
                $matched++;
            }
        }

        return [
            'count' => $items->count(),
            'imported' => $matched,
            'cost' => 0,
        ];
    }

    private function duplicateDetector(AiAgent $agent, AiAgentTask $task): array
    {
        $input = $task->input;
        $batchId = $input['batch_id'] ?? null;
        $maxResults = $input['max_results'] ?? 50;

        $query = ImportItem::where('status', 'pending');
        if ($batchId) {
            $query->where('batch_id', $batchId);
        }
        $items = $query->limit($maxResults)->get();

        $existing = Business::pluck('name')->map(fn($n) => Str::lower($n))->toArray();
        $duplicates = 0;

        foreach ($items as $item) {
            $name = Str::lower($item->data['name'] ?? '');
            if (in_array($name, $existing)) {
                $item->update(['status' => 'duplicate', 'notes' => 'Already exists in database']);
                $duplicates++;
            }
        }

        return [
            'count' => $items->count(),
            'imported' => $duplicates,
            'cost' => 0,
        ];
    }

    private function descriptionWriter(AiAgent $agent, AiAgentTask $task): array
    {
        $input = $task->input;
        $api = $this->getApiConfig($agent);
        $maxResults = $input['max_results'] ?? 10;

        if (!$api['api_key']) {
            throw new \Exception('API key not configured.');
        }

        $batchId = $input['batch_id'] ?? null;
        $items = ImportItem::where('status', 'pending')
            ->when($batchId, fn($q) => $q->where('batch_id', $batchId))
            ->whereNull('data->description')
            ->limit($maxResults)
            ->get();

        $updated = 0;

        foreach ($items as $item) {
            $name = $item->data['name'] ?? '';
            $address = $item->data['address'] ?? '';

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $api['api_key'],
            ])->timeout(30)->post($api['endpoint'], [
                'model' => $api['model'],
                'messages' => [
                    ['role' => 'user', 'content' => "Write a 2-sentence description for: {$name}, located at {$address}. Return ONLY the description text."],
                ],
                'temperature' => 0.7,
                'max_tokens' => 200,
            ]);

            if ($response->successful()) {
                $desc = trim($response->json('choices.0.message.content', ''));
                $data = $item->data;
                $data['description'] = $desc;
                $item->update(['data' => $data]);
                $updated++;
            }
        }

        return [
            'count' => $items->count(),
            'imported' => $updated,
            'cost' => 0.001,
        ];
    }

    private function qualityChecker(AiAgent $agent, AiAgentTask $task): array
    {
        $input = $task->input;
        $batchId = $input['batch_id'] ?? null;
        $maxResults = $input['max_results'] ?? 50;

        $query = ImportItem::where('status', 'pending');
        if ($batchId) {
            $query->where('batch_id', $batchId);
        }
        $items = $query->limit($maxResults)->get();

        $checked = 0;

        foreach ($items as $item) {
            $score = 0;
            $data = $item->data;

            if (!empty($data['name'])) $score += 20;
            if (!empty($data['address'])) $score += 20;
            if (!empty($data['phone'])) $score += 15;
            if (!empty($data['description'])) $score += 15;
            if (!empty($data['website'])) $score += 10;
            if (!empty($data['category'])) $score += 10;
            if (!empty($data['latitude'])) $score += 10;

            $item->update(['confidence' => $score / 100]);
            $checked++;
        }

        return [
            'count' => $items->count(),
            'imported' => $checked,
            'cost' => 0,
        ];
    }

    private function csvImporter(AiAgent $agent, AiAgentTask $task): array
    {
        $input = $task->input;
        $filePath = $input['file_path'] ?? null;

        if (!$filePath || !file_exists($filePath)) {
            throw new \Exception('CSV file not found.');
        }

        $csv = array_map('str_getcsv', file($filePath));
        $headers = array_map('strtolower', array_shift($csv));

        $batch = ImportBatch::create([
            'agent_id' => $agent->id,
            'source' => 'csv',
            'name' => 'CSV Import: ' . basename($filePath),
            'total' => count($csv),
            'status' => 'processing',
        ]);

        $imported = 0;

        foreach ($csv as $row) {
            $data = array_combine($headers, $row);

            if (empty($data['name'])) continue;

            ImportItem::create([
                'batch_id' => $batch->id,
                'data' => $data,
                'confidence' => 0.9,
            ]);

            $imported++;
        }

        $batch->update(['status' => 'completed']);

        return [
            'count' => count($csv),
            'imported' => $imported,
            'batch_id' => $batch->id,
            'cost' => 0,
        ];
    }

    private function parseJsonFromResponse(string $content): ?array
    {
        $content = trim($content);

        // Try to extract JSON from markdown code blocks
        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $content, $matches)) {
            $content = trim($matches[1]);
        }

        // Try to find JSON array
        if (preg_match('/\[[\s\S]*\]/', $content, $matches)) {
            $content = $matches[0];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function matchCategory(array $placeTypes, array $categoryNames): ?string
    {
        $typeMap = [
            'restaurant' => ['restaurant', 'food', 'meal_takeaway'],
            'school' => ['school', 'university'],
            'hospital' => ['hospital', 'doctor', 'health'],
            'shop' => ['store', 'shop', 'clothing_store'],
            'hotel' => ['hotel', 'lodging'],
            'bank' => ['bank', 'atm', 'finance'],
            'pharmacy' => ['pharmacy', 'drugstore'],
            'gym' => ['gym', 'fitness_center'],
            'church' => ['church', 'place_of_worship'],
        ];

        foreach ($placeTypes as $type) {
            foreach ($typeMap as $category => $types) {
                if (in_array($type, $types)) {
                    $match = collect($categoryNames)->first(fn($cn) => Str::contains(Str::lower($cn), $category));
                    if ($match) return $match;
                }
            }
        }

        return null;
    }
}
