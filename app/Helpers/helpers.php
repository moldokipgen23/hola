<?php

use App\Models\Category;
use App\Models\Subcategory;

if (! function_exists('matchImportCategory')) {
    /**
     * Smart category matching for imports.
     * Maps Google's category names to our existing categories.
     * Returns ['category_id' => int, 'subcategory_id' => int|null]
     */
    function matchImportCategory(?string $rawCategory, array $categories): int
    {
        if (! $rawCategory) {
            return resolveFallbackImportCategoryId();
        }

        $rawLower = strtolower(trim($rawCategory));

        // 1. Exact match
        foreach ($categories as $name => $catId) {
            if (strtolower($name) === $rawLower) {
                return $catId;
            }
        }

        // 2. Specific mappings: pharmacy/drugstore → Healthcare (subcategory Pharmacies handled separately)
        $specificMappings = [
            'pharmacy' => 'Healthcare',
            'drugstore' => 'Healthcare',
            'pharmacies' => 'Healthcare',
            'medical_store' => 'Healthcare',
            'medical store' => 'Healthcare',
        ];
        foreach ($specificMappings as $keyword => $targetCat) {
            if (str_contains($rawLower, $keyword)) {
                $match = collect($categories)->first(fn ($n) => strtolower($n) === strtolower($targetCat));
                if ($match) {
                    return $categories[$match];
                }
            }
        }

        // 3. Smart mapping: Google category → our category
        $educationKeywords = ['school', 'college', 'university', 'academy', 'education', 'preschool',
            'kindergarten', 'nursery', 'institute', 'seminary', 'lyceum', 'polytechnic',
            'high school', 'primary school', 'secondary school', 'elementary', 'cbse', 'icse'];
        $foodKeywords = ['restaurant', 'cafe', 'food', 'dining', 'bakery', 'bar', 'pub', 'coffee',
            'tea', 'canteen', 'eatery', 'kitchen', 'bistro', 'pizzeria', 'fast food'];
        $healthKeywords = ['hospital', 'clinic', 'medical', 'health', 'doctor', 'dental',
            'diagnostic', 'laboratory', 'wellness', 'nursing', 'ayurvedic', 'homoeopathic'];
        $hotelKeywords = ['hotel', 'lodge', 'guest house', 'resort', 'inn', 'hostel', 'motel', 'homestay'];
        $shoppingKeywords = ['store', 'shop', 'market', 'mall', 'retail', 'supermarket', 'grocery',
            'boutique', 'emporium', 'mart', 'bazaar'];
        $beautyKeywords = ['salon', 'beauty', 'spa', 'parlor', 'hair', 'nail', 'cosmetic'];
        $autoKeywords = ['auto', 'car', 'vehicle', 'garage', 'mechanic', 'petrol', 'fuel', 'tyre'];
        $techKeywords = ['electronics', 'computer', 'mobile', 'phone', 'repair', 'internet', 'cyber'];
        $sportKeywords = ['gym', 'fitness', 'sports', 'stadium', 'ground', 'playground', 'yoga',
            'football', 'turf', 'swimming', 'pool', 'picnic', 'amusement'];
        $proKeywords = ['bank', 'insurance', 'finance', 'ca', 'chartered', 'legal', 'law', 'advocate',
            'consultant', 'agency', 'real estate', 'travel', 'tour'];

        $mapping = [
            'education' => $educationKeywords,
            'food & restaurants' => $foodKeywords,
            'healthcare' => $healthKeywords,
            'hotels & lodges' => $hotelKeywords,
            'shopping & retail' => $shoppingKeywords,
            'beauty & wellness' => $beautyKeywords,
            'automobiles' => $autoKeywords,
            'electronics & tech' => $techKeywords,
            'sports & fitness' => $sportKeywords,
            'professional services' => $proKeywords,
        ];

        foreach ($mapping as $targetCat => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($rawLower, $kw)) {
                    $match = collect($categories)->first(fn ($n) => strtolower($n) === $targetCat);
                    if ($match) {
                        return $categories[$match];
                    }
                }
            }
        }

        // 4. Fuzzy: check if any existing category name is contained in the raw category
        foreach ($categories as $name => $catId) {
            if (str_contains($rawLower, strtolower($name))) {
                return $catId;
            }
        }

        // 5. No match — never mutate taxonomy during import.
        return resolveFallbackImportCategoryId();
    }
}

if (! function_exists('resolveFallbackImportCategoryId')) {
    function resolveFallbackImportCategoryId(): int
    {
        $categoryId = Category::where('slug', 'uncategorized')->value('id')
            ?? Category::where('slug', 'general')->value('id')
            ?? Category::active()->where('is_canonical', true)->orderBy('id')->value('id')
            ?? Category::orderBy('id')->value('id');

        if (! $categoryId) {
            throw new RuntimeException('No admin-approved category exists for this import.');
        }

        return (int) $categoryId;
    }
}

if (! function_exists('resolveApprovedImportTaxonomy')) {
    /**
     * Resolve an import only to an active, admin-approved category. AI agents
     * store category_id/subcategory_id after matching source types; approval
     * must use those IDs rather than guess again from a display label.
     *
     * Unknown records deliberately return null and stay in review. They must
     * never silently become "General", "Establishment", or another unrelated
     * customer journey.
     */
    function resolveApprovedImportTaxonomy(array $data): ?array
    {
        $category = null;
        $categoryId = $data['category_id'] ?? null;

        if ($categoryId) {
            $category = Category::active()->where('is_canonical', true)->find($categoryId);
        }

        if (! $category && ! empty($data['category_slug'])) {
            $category = Category::active()->where('is_canonical', true)
                ->where('slug', $data['category_slug'])->first();
        }

        if (! $category && ! empty($data['category'])) {
            $category = Category::active()->where('is_canonical', true)
                ->whereRaw('LOWER(name) = ?', [strtolower(trim((string) $data['category']))])
                ->first();
        }

        if (! $category) {
            return null;
        }

        $subcategory = null;
        if (! empty($data['subcategory_id'])) {
            $subcategory = Subcategory::active()
                ->where('category_id', $category->id)
                ->find($data['subcategory_id']);
        }

        if (! $subcategory && ! empty($data['subcategory_slug'])) {
            $subcategory = Subcategory::active()
                ->where('category_id', $category->id)
                ->where('slug', $data['subcategory_slug'])
                ->first();
        }

        return [
            'category_id' => $category->id,
            'subcategory_id' => $subcategory?->id,
        ];
    }
}

if (! function_exists('matchImportSubcategory')) {
    /**
     * Match subcategory based on business name/address/types.
     * Returns subcategory_id or null.
     */
    function matchImportSubcategory(?string $rawCategory, ?string $businessName, int $categoryId): ?int
    {
        $rawLower = strtolower(trim($rawCategory ?? ''));
        $nameLower = strtolower(trim($businessName ?? ''));
        $categorySlug = Category::whereKey($categoryId)->value('slug');

        // Load subcategories for this category
        $subcategories = Subcategory::where('category_id', $categoryId)
            ->where('is_active', true)
            ->get()
            ->keyBy(fn ($s) => strtolower($s->name));

        if ($subcategories->isEmpty()) {
            return null;
        }

        // Pharmacy matching
        if ($categorySlug === 'healthcare') {
            foreach (['pharmacy', 'pharmacies', 'medical store', 'drugstore', 'chemist', 'medicine'] as $kw) {
                if (str_contains($rawLower, $kw) || str_contains($nameLower, $kw)) {
                    return $subcategories->get('pharmacies')?->id
                        ?? $subcategories->first(fn ($subcategory) => str_contains(strtolower($subcategory->name), 'pharm'))?->id;
                }
            }
            foreach (['hospital'] as $kw) {
                if (str_contains($rawLower, $kw) || str_contains($nameLower, $kw)) {
                    return $subcategories->get('hospitals')?->id ?? null;
                }
            }
            foreach (['clinic', ' clinic'] as $kw) {
                if (str_contains($rawLower, $kw) || str_contains($nameLower, $kw)) {
                    return $subcategories->get('clinics')?->id ?? null;
                }
            }
            foreach (['dental', 'dentist', 'teeth'] as $kw) {
                if (str_contains($rawLower, $kw) || str_contains($nameLower, $kw)) {
                    return $subcategories->get('dental')?->id ?? null;
                }
            }
            foreach (['diagnostic', 'lab', 'pathology', 'test'] as $kw) {
                if (str_contains($rawLower, $kw) || str_contains($nameLower, $kw)) {
                    return $subcategories->get('diagnostic centres')?->id
                        ?? $subcategories->get('diagnostic lab')?->id;
                }
            }
        }

        // Hotels matching
        if ($categorySlug === 'hotels-lodges') {
            foreach (['resort'] as $kw) {
                if (str_contains($rawLower, $kw) || str_contains($nameLower, $kw)) {
                    return $subcategories->get('resorts')?->id ?? null;
                }
            }
            foreach (['guest house', 'guesthouse'] as $kw) {
                if (str_contains($rawLower, $kw) || str_contains($nameLower, $kw)) {
                    return $subcategories->get('guest houses')?->id ?? null;
                }
            }
            foreach (['homestay', 'home stay'] as $kw) {
                if (str_contains($rawLower, $kw) || str_contains($nameLower, $kw)) {
                    return $subcategories->get('homestays')?->id ?? null;
                }
            }

            // Default to Hotels
            return $subcategories->get('hotels')?->id ?? null;
        }

        // Food matching
        if ($categorySlug === 'food-restaurants') {
            foreach (['cafe', 'coffee'] as $kw) {
                if (str_contains($rawLower, $kw) || str_contains($nameLower, $kw)) {
                    return $subcategories->get('cafes')?->id ?? null;
                }
            }
            foreach (['bakery', 'bread', 'cake'] as $kw) {
                if (str_contains($rawLower, $kw) || str_contains($nameLower, $kw)) {
                    return $subcategories->get('bakeries')?->id ?? null;
                }
            }
            foreach (['fast food', 'burger', 'pizza', 'fried chicken'] as $kw) {
                if (str_contains($rawLower, $kw) || str_contains($nameLower, $kw)) {
                    return $subcategories->get('fast food')?->id ?? null;
                }
            }
            foreach (['catering', 'caterer'] as $kw) {
                if (str_contains($rawLower, $kw) || str_contains($nameLower, $kw)) {
                    return $subcategories->get('catering')?->id ?? null;
                }
            }

            // Default to Restaurants
            return $subcategories->get('restaurants')?->id ?? null;
        }

        // Education matching
        if ($categorySlug === 'education') {
            foreach (['college'] as $kw) {
                if (str_contains($rawLower, $kw) || str_contains($nameLower, $kw)) {
                    return $subcategories->get('colleges')?->id ?? null;
                }
            }
            foreach (['tuition', 'coaching', 'tutorial'] as $kw) {
                if (str_contains($rawLower, $kw) || str_contains($nameLower, $kw)) {
                    return $subcategories->get('tuition centers')?->id ?? null;
                }
            }

            // Default to Schools
            return $subcategories->get('schools')?->id ?? null;
        }

        // Shopping matching
        if ($categorySlug === 'shopping-retail') {
            foreach (['mall', 'shopping mall'] as $kw) {
                if (str_contains($rawLower, $kw) || str_contains($nameLower, $kw)) {
                    return $subcategories->get('shopping mall')?->id ?? null;
                }
            }
            foreach (['electronics', 'computer', 'mobile', 'phone', 'laptop'] as $kw) {
                if (str_contains($rawLower, $kw) || str_contains($nameLower, $kw)) {
                    return $subcategories->get('electronics store')?->id ?? null;
                }
            }
            foreach (['grocery', 'kirana', 'provision'] as $kw) {
                if (str_contains($rawLower, $kw) || str_contains($nameLower, $kw)) {
                    return $subcategories->get('grocery stores')?->id ?? null;
                }
            }
            foreach (['cloth', 'garment', 'fashion', 'wear'] as $kw) {
                if (str_contains($rawLower, $kw) || str_contains($nameLower, $kw)) {
                    return $subcategories->get('clothing')?->id ?? null;
                }
            }
            foreach (['hardware', 'paint', 'plumbing'] as $kw) {
                if (str_contains($rawLower, $kw) || str_contains($nameLower, $kw)) {
                    return $subcategories->get('hardware stores')?->id ?? null;
                }
            }
            foreach (['stationery', 'book', 'paper'] as $kw) {
                if (str_contains($rawLower, $kw) || str_contains($nameLower, $kw)) {
                    return $subcategories->get('stationery')?->id ?? null;
                }
            }
        }

        // Sports matching
        if ($categorySlug === 'sports-fitness') {
            foreach (['football', 'turf', 'soccer'] as $kw) {
                if (str_contains($rawLower, $kw) || str_contains($nameLower, $kw)) {
                    return $subcategories->get('turfs & sports grounds')?->id
                        ?? $subcategories->get('football turf')?->id;
                }
            }
            foreach (['swimming', 'pool', 'aquatic'] as $kw) {
                if (str_contains($rawLower, $kw) || str_contains($nameLower, $kw)) {
                    return $subcategories->get('swimming pools')?->id
                        ?? $subcategories->get('swimming pool')?->id;
                }
            }
            foreach (['picnic', 'park', 'amusement'] as $kw) {
                if (str_contains($rawLower, $kw) || str_contains($nameLower, $kw)) {
                    return $subcategories->get('picnic spot')?->id ?? $subcategories->get('amusement park')?->id ?? null;
                }
            }
            foreach (['gym', 'fitness'] as $kw) {
                if (str_contains($rawLower, $kw) || str_contains($nameLower, $kw)) {
                    return $subcategories->get('gyms')?->id ?? null;
                }
            }
        }

        // Electronics matching
        if ($categorySlug === 'electronics-tech') {
            foreach (['mobile', 'phone', 'smartphone'] as $kw) {
                if (str_contains($rawLower, $kw) || str_contains($nameLower, $kw)) {
                    return $subcategories->get('mobile shops')?->id ?? null;
                }
            }
            foreach (['computer', 'laptop', 'pc', 'desktop'] as $kw) {
                if (str_contains($rawLower, $kw) || str_contains($nameLower, $kw)) {
                    return $subcategories->get('computer stores')?->id ?? null;
                }
            }
            foreach (['repair', 'service center'] as $kw) {
                if (str_contains($rawLower, $kw) || str_contains($nameLower, $kw)) {
                    return $subcategories->get('repair shops')?->id ?? null;
                }
            }
        }

        return null;
    }
}
