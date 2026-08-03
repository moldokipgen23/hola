<?php

namespace App\Services;

use App\Models\AiAgent;
use App\Models\Business;
use App\Models\ImportItem;
use App\Models\SourceTaxonomyMapping;
use App\Models\TaxonomySuggestion;
use Illuminate\Support\Str;

class TaxonomyService
{
    public function resolveProviderTypes(string $provider, array $sourceTypes): ?array
    {
        $sourceTypes = array_values(array_unique(array_filter($sourceTypes)));

        if ($sourceTypes === []) {
            return null;
        }

        $mappings = SourceTaxonomyMapping::query()
            ->with(['category:id,name,slug', 'subcategory:id,category_id,name,slug'])
            ->where('provider', $provider)
            ->where('is_active', true)
            ->whereIn('source_type', $sourceTypes)
            ->get()
            ->sortBy(fn (SourceTaxonomyMapping $mapping) => array_search($mapping->source_type, $sourceTypes, true));

        $mapping = $mappings->first();

        if (! $mapping || ! $mapping->category) {
            return null;
        }

        return [
            'category_id' => $mapping->category_id,
            'category' => $mapping->category->name,
            'category_slug' => $mapping->category->slug,
            'subcategory_id' => $mapping->subcategory_id,
            'subcategory' => $mapping->subcategory?->name,
            'subcategory_slug' => $mapping->subcategory?->slug,
            'recommended_modules' => $mapping->recommended_modules ?? [],
            'classification_confidence' => (float) $mapping->confidence,
            'matched_source_type' => $mapping->source_type,
        ];
    }

    public function suggestUnknown(
        string $suggestedName,
        ?AiAgent $agent = null,
        ?ImportItem $importItem = null,
        ?Business $business = null,
        array $evidence = [],
        ?string $sourceProvider = null,
        ?string $sourceType = null,
        ?float $confidence = null,
    ): TaxonomySuggestion {
        $name = Str::of($suggestedName)->replace('_', ' ')->squish()->title()->toString();

        return TaxonomySuggestion::firstOrCreate(
            [
                'suggestion_type' => 'category',
                'suggested_name' => $name,
                'source_provider' => $sourceProvider,
                'source_type' => $sourceType,
                'status' => 'pending',
            ],
            [
                'agent_id' => $agent?->id,
                'import_item_id' => $importItem?->id,
                'business_id' => $business?->id,
                'evidence' => $evidence,
                'confidence' => $confidence,
            ]
        );
    }
}
