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
                'serpapi_business_search' => $this->serpapiBusinessSearch($agent, $task),
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
        $apiKey = \App\Models\Setting::get('api_key_google_places')
            ?? config('services.google.places_api_key');

        if (!$apiKey) {
            throw new \Exception('Google Places API key not configured.');
        }

        $query = $input['query'] ?? '';
        $area = $input['area'] ?? '';
        $zipcode = $input['zipcode'] ?? '';
        $maxResults = min($input['max_results'] ?? 20, 60);

        // Build search query from area + zipcode + query
        $location = implode(' ', array_filter([$area, $zipcode]));
        $searchQuery = $query . ($location ? ' in ' . $location : '');

        // Text Search with pagination (Google returns max 20 per page)
        $places = [];
        $pageToken = null;
        $maxPages = 3; // 3 pages x 20 = 60 max

        for ($page = 0; $page < $maxPages && count($places) < $maxResults; $page++) {
            $params = [
                'query' => $searchQuery,
                'key' => $apiKey,
            ];
            if ($pageToken) {
                $params['pagetoken'] = $pageToken;
            }

            $response = Http::get('https://maps.googleapis.com/maps/api/place/textsearch/json', $params);

            if ($response->failed()) {
                throw new \Exception('Google Places API request failed: ' . $response->body());
            }

            $data = $response->json();

            if (($data['status'] ?? '') !== 'OK' && ($data['status'] ?? '') !== 'ZERO_RESULTS') {
                if ($page === 0) {
                    $errorMsg = $data['error_message'] ?? $data['status'] ?? 'Unknown error';
                    throw new \Exception('Google Places API error: ' . $errorMsg);
                }
                break;
            }

            $places = array_merge($places, $data['results'] ?? []);
            $pageToken = $data['next_page_token'] ?? null;

            if (!$pageToken) {
                break;
            }

            // Google requires a short delay before using next_page_token
            usleep(2000000);
        }

        $places = array_slice($places, 0, $maxResults);

        $batch = ImportBatch::create([
            'agent_id' => $agent->id,
            'source' => 'google_places',
            'name' => "Google: {$searchQuery}",
            'total' => count($places),
            'status' => 'processing',
        ]);

        $imported = 0;
        $skipped = 0;
        $categories = Category::pluck('id', 'name')->toArray();

        // Pre-load existing ACTIVE businesses for duplicate detection (exclude soft-deleted)
        $existingPlaceIds = Business::withoutTrashed()->whereNotNull('external_id')->pluck('external_id')->toArray();
        $existingNames = Business::withoutTrashed()->pluck('name')->map(fn($n) => Str::lower($n))->toArray();

        foreach ($places as $place) {
            $placeId = $place['place_id'] ?? null;
            $name = $place['name'] ?? '';
            $address = $place['formatted_address'] ?? $place['vicinity'] ?? '';

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
                $existingWithSameName = Business::withoutTrashed()->whereRaw('LOWER(name) = ?', [$normalizedName])->first();
                if ($existingWithSameName && Str::contains(Str::lower($existingWithSameName->address), substr($normalizedAddress, 0, 10))) {
                    $skipped++;
                    continue;
                }
            }

            // QUALITY CHECK: Skip places without address
            if (strlen($address) < 5) {
                $skipped++;
                continue;
            }

            // Fetch place details for phone, website, photos, working hours
            $phone = null;
            $website = null;
            $photoUrls = [];
            $workingHours = null;
            if ($placeId) {
                try {
                    $details = Http::get('https://maps.googleapis.com/maps/api/place/details/json', [
                        'place_id' => $placeId,
                        'fields' => 'formatted_phone_number,website,opening_hours,photos,reviews,address_components',
                        'key' => $apiKey,
                    ]);
                    if ($details->successful()) {
                        $detailData = $details->json('result', []);
                        $phone = $detailData['formatted_phone_number'] ?? null;
                        $website = $detailData['website'] ?? null;

                        // Extract working hours
                        if (!empty($detailData['opening_hours']['weekday_text'])) {
                            $hoursMap = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                            $workingHours = [];
                            foreach ($detailData['opening_hours']['weekday_text'] as $index => $text) {
                                $day = strtolower($hoursMap[$index] ?? '');
                                if (Str::contains($text, 'Closed')) {
                                    $workingHours[$day] = ['open' => null, 'close' => null];
                                } else {
                                    $parts = explode(':', $text, 2);
                                    $times = trim($parts[1] ?? '');
                                    $timeParts = explode('–', $times);
                                    $workingHours[$day] = [
                                        'open' => trim($timeParts[0] ?? ''),
                                        'close' => trim($timeParts[1] ?? ''),
                                    ];
                                }
                            }
                        }

                        if (!empty($detailData['photos'])) {
                            foreach (array_slice($detailData['photos'], 0, 3) as $photo) {
                                if (!empty($photo['photo_reference'])) {
                                    $photoUrls[] = "https://maps.googleapis.com/maps/api/place/photo?photoreference={$photo['photo_reference']}&maxwidth=800&key={$apiKey}";
                                }
                            }
                        }
                        // Capture reviews
                        $googleReviews = [];
                        if (!empty($detailData['reviews'])) {
                            foreach (array_slice($detailData['reviews'], 0, 5) as $rev) {
                                $googleReviews[] = [
                                    'author' => $rev['author_name'] ?? 'Anonymous',
                                    'rating' => $rev['rating'] ?? 0,
                                    'text' => $rev['text'] ?? '',
                                    'time' => $rev['time'] ?? null,
                                ];
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Continue without details
                }
            }

            // Extract locality and district from address_components
            $locality = null;
            $district = null;
            $addressComponents = $place['geometry']['address_components'] ?? [];
            if (empty($addressComponents) && isset($detailData['address_components'])) {
                $addressComponents = $detailData['address_components'];
            }
            foreach ($addressComponents as $component) {
                $types = $component['types'] ?? [];
                if (in_array('locality', $types)) {
                    $locality = $component['long_name'] ?? null;
                }
                if (in_array('administrative_area_level_2', $types)) {
                    $district = $component['long_name'] ?? null;
                }
            }

            $placeData = [
                'name' => $name,
                'address' => $address,
                'locality' => $locality,
                'district' => $district ?? 'Churachandpur',
                'latitude' => $place['geometry']['location']['lat'] ?? null,
                'longitude' => $place['geometry']['location']['lng'] ?? null,
                'phone' => $phone,
                'website' => $website,
                'rating' => $place['rating'] ?? null,
                'total_ratings' => $place['user_ratings_total'] ?? 0,
                'types' => $place['types'] ?? [],
                'google_place_id' => $placeId,
                'photos' => $photoUrls,
                'working_hours' => $workingHours,
                'google_reviews' => $googleReviews ?? [],
                'photo_references' => array_map(fn($p) => $p['photo_reference'] ?? null, $place['photos'] ?? []),
            ];

            // Auto-match category
            $matchedCategory = $this->matchCategory($place['types'] ?? [], array_keys($categories));
            if ($matchedCategory) {
                $placeData['category'] = $matchedCategory;
            }

            // Calculate confidence based on data quality
            $rating = $place['rating'] ?? null;
            $confidence = 0.6;
            if ($phone) $confidence += 0.1;
            if ($website) $confidence += 0.1;
            if ($rating && $rating >= 4.0) $confidence += 0.1;
            if (($place['user_ratings_total'] ?? 0) >= 10) $confidence += 0.1;
            if (count($photoUrls) > 0) $confidence += 0.1;
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
You are a business directory researcher. List ONLY verified, real businesses that physically exist in {$area}, India. Category: {$category}.

CRITICAL RULES:
- ONLY include businesses you are CERTAIN exist at this location
- Include SPECIFIC addresses, real phone numbers, real names
- If you don't know the phone number, use null — NEVER make one up
- If you're unsure about any business, SKIP it entirely
- Fewer REAL results is better than more FAKE results
- Maximum {$maxResults} businesses

Return ONLY a JSON array with these fields:
- name: exact business name
- address: full street address
- locality: the locality/area within the city (e.g. "Lamka", "Haipauzen")
- phone: real phone number or null
- description: what they do (1 sentence)
- website: website URL or null
- category: from this list: {$categoryList}
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
                'locality' => $biz['locality'] ?? null,
                'district' => 'Churachandpur',
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

        $tokens = $result['usage']['total_tokens'] ?? 0;
        $cost = $tokens * 0.00000014;

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
        $api = $this->getApiConfig($agent);
        $batchId = $input['batch_id'] ?? null;
        $maxResults = $input['max_results'] ?? 30;

        if (!$api['api_key']) {
            throw new \Exception('API key not configured for AI categorization.');
        }

        $query = ImportItem::where('status', 'pending')->whereNull('data->category');
        if ($batchId) $query->where('batch_id', $batchId);
        $items = $query->limit($maxResults)->get();

        if ($items->isEmpty()) {
            return ['count' => 0, 'imported' => 0, 'cost' => 0];
        }

        $existingCategories = Category::pluck('name')->toArray();
        $catList = !empty($existingCategories) ? implode("\n- ", $existingCategories) : 'No categories exist yet. Create new ones.';

        $businessList = [];
        foreach ($items as $item) {
            $d = $item->data;
            $types = $d['types'] ?? [];
            $businessList[] = [
                'id' => $item->id,
                'name' => $d['name'] ?? '',
                'address' => $d['address'] ?? '',
                'types' => $types,
                'description' => substr($d['description'] ?? '', 0, 100),
            ];
        }

        $json = json_encode($businessList, JSON_PRETTY_PRINT);

        $prompt = <<<EOT
You are a business categorization expert. For each business below, pick the BEST matching category.

EXISTING CATEGORIES:
- {$catList}

If NO existing category fits, suggest a NEW category name (make it concise, e.g., "Tuition Center", "Hardware Store").

Return ONLY a JSON array with: id, category (the category name), is_new (true if you created a new category).

Businesses:
{$json}
EOT;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $api['api_key'],
            'Content-Type' => 'application/json',
        ])->timeout(60)->post($api['endpoint'], [
            'model' => $api['model'],
            'messages' => [
                ['role' => 'system', 'content' => 'You are a business categorization AI. Return only valid JSON array. Be precise.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.2,
            'max_tokens' => 2000,
        ]);

        if ($response->failed()) {
            throw new \Exception('AI categorization failed: ' . $response->body());
        }

        $result = $response->json();
        $content = $result['choices'][0]['message']['content'] ?? '';
        $mappings = $this->parseJsonFromResponse($content);

        if (!is_array($mappings)) {
            throw new \Exception('AI categorization returned invalid JSON');
        }

        $categorized = 0;
        $created = [];

        foreach ($mappings as $map) {
            $itemId = $map['id'] ?? null;
            $catName = $map['category'] ?? null;
            $isNew = $map['is_new'] ?? false;

            if (!$itemId || !$catName) continue;

            // Create category if new
            if ($isNew && !in_array($catName, $existingCategories) && !in_array($catName, $created)) {
                Category::create([
                    'name' => $catName,
                    'slug' => Str::slug($catName),
                    'icon' => '📂',
                    'is_active' => true,
                ]);
                $created[] = $catName;
                $existingCategories[] = $catName;
            }

            $item = $items->firstWhere('id', $itemId);
            if ($item) {
                $data = $item->data;
                $data['category'] = $catName;
                $item->update([
                    'data' => $data,
                    'confidence' => min(($item->confidence ?? 0.5) + 0.15, 1.0),
                ]);
                $categorized++;
            }
        }

        return [
            'count' => $items->count(),
            'imported' => $categorized,
            'cost' => round(($result['usage']['total_tokens'] ?? 0) * 0.00000014, 4),
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
            'Restaurant' => ['restaurant', 'food', 'meal_takeaway', 'meal_delivery', 'cafe', 'bakery', 'bar'],
            'School' => ['school', 'primary_school', 'secondary_school', 'university', 'college'],
            'Hospital' => ['hospital', 'doctor', 'health', 'dentist', 'pharmacy', 'drugstore', 'physiotherapist'],
            'Hotel' => ['hotel', 'lodging', 'guest_house', 'motel', 'resort'],
            'Bank' => ['bank', 'atm', 'finance', 'insurance_agency'],
            'Shop' => ['store', 'shopping_mall', 'supermarket', 'grocery_or_supermarket', 'clothing_store', 'electronics_store', 'hardware_store', 'furniture_store', 'jewelry_store', 'shoe_store', 'book_store', 'department_store', 'home_goods_store'],
            'Gym' => ['gym', 'fitness_center', 'stadium'],
            'Church' => ['church', 'place_of_worship', 'hindu_temple', 'mosque'],
            'Gas Station' => ['gas_station', 'petrol_station'],
            'Beauty' => ['beauty_salon', 'hair_care', 'spa', 'nail_salon'],
            'Auto Repair' => ['car_repair', 'car_dealer', 'car_wash', 'auto_parts_store'],
            'Park' => ['park', 'tourist_attraction', 'museum', 'art_gallery', 'zoo'],
            'Real Estate' => ['real_estate_agency', 'travel_agency'],
            'Government' => ['local_government_office', 'police', 'fire_station', 'post_office', 'courthouse'],
            'Education' => ['school', 'primary_school', 'secondary_school', 'university', 'college', 'library'],
        ];

        foreach ($placeTypes as $type) {
            foreach ($typeMap as $category => $types) {
                if (in_array($type, $types)) {
                    $match = collect($categoryNames)->first(fn($cn) => Str::contains(Str::lower($cn), Str::lower($category)));
                    if ($match) return $match;
                }
            }
        }

        return null;
    }

    private function serpapiBusinessSearch(AiAgent $agent, AiAgentTask $task): array
    {
        $input = $task->input;
        $apiKey = \App\Models\Setting::get('api_key_serpapi')
            ?? config('services.serpapi.api_key');

        if (!$apiKey) {
            throw new \Exception('SerpAPI key not configured. Add it in Settings → API Keys or on this agent.');
        }

        $query = $input['query'] ?? 'businesses';
        $area = $input['area'] ?? '';
        $zipcode = $input['zipcode'] ?? '';
        $maxResults = min($input['max_results'] ?? 20, 50);

        $location = implode(' ', array_filter([$area, $zipcode]));
        $searchQuery = $query . ($location ? ' in ' . $location : '');

        $response = Http::get('https://serpapi.com/search', [
            'engine' => 'google_maps',
            'q' => $searchQuery,
            'api_key' => $apiKey,
            'hl' => 'en',
            'num' => min($maxResults, 20),
        ]);

        if ($response->failed()) {
            throw new \Exception('SerpAPI request failed: ' . $response->body());
        }

        $data = $response->json();
        $places = $data['local_results'] ?? [];

        if (empty($places)) {
            return ['count' => 0, 'imported' => 0, 'skipped' => 0, 'batch_id' => null, 'cost' => 0];
        }

        $batch = ImportBatch::create([
            'agent_id' => $agent->id,
            'source' => 'serpapi',
            'name' => "SerpAPI: {$searchQuery}",
            'total' => count($places),
            'status' => 'processing',
        ]);

        $imported = 0;
        $skipped = 0;
        $existingNames = Business::pluck('name')->map(fn($n) => Str::lower($n))->toArray();

        foreach (array_slice($places, 0, $maxResults) as $place) {
            $name = $place['title'] ?? '';
            $address = $place['address'] ?? '';
            $phone = $place['phone'] ?? null;
            $website = $place['website'] ?? $place['link'] ?? null;
            $rating = $place['rating'] ?? null;
            $reviews = $place['reviews'] ?? null;
            $type = $place['type'] ?? null;

            if (empty($name) || strlen($name) < 3) {
                $skipped++;
                continue;
            }

            // Clean name - remove rating numbers at start like "4.5"
            $cleanName = preg_replace('/^[\d.]+\s*/', '', $name);

            $normalizedName = Str::lower(trim($cleanName));
            if (in_array($normalizedName, $existingNames)) {
                $skipped++;
                continue;
            }

            $placeData = [
                'name' => $cleanName,
                'address' => $address,
                'phone' => $phone,
                'website' => $website,
                'rating' => $rating,
                'total_ratings' => $reviews,
                'category' => is_array($type) ? ($type[0] ?? null) : $type,
                'latitude' => $place['gps_coordinates']['latitude'] ?? null,
                'longitude' => $place['gps_coordinates']['longitude'] ?? null,
            ];

            $confidence = 0.5;
            if ($phone) $confidence += 0.15;
            if ($website) $confidence += 0.1;
            if ($address) $confidence += 0.15;
            if ($rating) $confidence += 0.1;
            $confidence = min($confidence, 1.0);

            ImportItem::create([
                'batch_id' => $batch->id,
                'data' => $placeData,
                'confidence' => $confidence,
            ]);

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
            'cost' => round(count($places) * 0.005, 4),
        ];
    }
}
