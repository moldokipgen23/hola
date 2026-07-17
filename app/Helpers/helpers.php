<?php

use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Support\Str;

if (! function_exists('matchImportCategory')) {
    /**
     * Smart category matching for imports.
     * Maps Google's category names to our existing categories.
     * Returns ['category_id' => int, 'subcategory_id' => int|null]
     */
    function matchImportCategory(?string $rawCategory, array $categories): int
    {
        if (! $rawCategory) {
            return Category::where('slug', 'general')->value('id')
                ?? Category::firstOrCreate(['name' => 'General', 'slug' => 'general'])->id;
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

        // 5. No match — create new category
        $slug = Str::slug($rawCategory);
        $cat = Category::firstOrCreate(
            ['name' => $rawCategory, 'slug' => $slug],
            ['description' => 'Auto-created from import']
        );

        return $cat->id;
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

        // Load subcategories for this category
        $subcategories = Subcategory::where('category_id', $categoryId)
            ->where('is_active', true)
            ->get()
            ->keyBy(fn ($s) => strtolower($s->name));

        if ($subcategories->isEmpty()) {
            return null;
        }

        // Pharmacy matching
        if ($categoryId === 3) { // Healthcare
            foreach (['pharmacy', 'pharmacies', 'medical store', 'drugstore', 'chemist', 'medicine'] as $kw) {
                if (str_contains($rawLower, $kw) || str_contains($nameLower, $kw)) {
                    return $subcategories->get('pharmacies')?->id
                        ?? $subcategories->keys()->first(fn ($k) => str_contains($k, 'pharm')) ? $subcategories->first(fn ($s) => str_contains(strtolower($s->name), 'pharm'))?->id : null;
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
                    return $subcategories->get('diagnostic lab')?->id ?? null;
                }
            }
        }

        // Hotels matching
        if ($categoryId === 2) {
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
        if ($categoryId === 1) {
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
        if ($categoryId === 4) {
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
        if ($categoryId === 5) {
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
        if ($categoryId === 10) {
            foreach (['football', 'turf', 'soccer'] as $kw) {
                if (str_contains($rawLower, $kw) || str_contains($nameLower, $kw)) {
                    return $subcategories->get('football turf')?->id ?? null;
                }
            }
            foreach (['swimming', 'pool', 'aquatic'] as $kw) {
                if (str_contains($rawLower, $kw) || str_contains($nameLower, $kw)) {
                    return $subcategories->get('swimming pool')?->id ?? null;
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
        if ($categoryId === 6) {
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
