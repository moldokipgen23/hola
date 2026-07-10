<?php

if (!function_exists('matchImportCategory')) {
    /**
     * Smart category matching for imports.
     * Maps Google's category names to our existing categories.
     */
    function matchImportCategory(?string $rawCategory, array $categories): int
    {
        if (!$rawCategory) {
            return \App\Models\Category::where('slug', 'general')->value('id')
                ?? \App\Models\Category::firstOrCreate(['name' => 'General', 'slug' => 'general'])->id;
        }

        $rawLower = strtolower(trim($rawCategory));

        // 1. Exact match
        foreach ($categories as $name => $catId) {
            if (strtolower($name) === $rawLower) {
                return $catId;
            }
        }

        // 2. Smart mapping: Google category → our category
        $educationKeywords = ['school', 'college', 'university', 'academy', 'education', 'preschool',
            'kindergarten', 'nursery', 'institute', 'seminary', 'lyceum', 'polytechnic',
            'high school', 'primary school', 'secondary school', 'elementary', 'cbse', 'icse'];
        $foodKeywords = ['restaurant', 'cafe', 'food', 'dining', 'bakery', 'bar', 'pub', 'coffee',
            'tea', 'canteen', 'eatery', 'kitchen', 'bistro', 'pizzeria', 'fast food'];
        $healthKeywords = ['hospital', 'clinic', 'pharmacy', 'medical', 'health', 'doctor', 'dental',
            'diagnostic', 'laboratory', 'wellness', 'nursing', 'ayurvedic', 'homoeopathic'];
        $hotelKeywords = ['hotel', 'lodge', 'guest house', 'resort', 'inn', 'hostel', 'motel', 'homestay'];
        $shoppingKeywords = ['store', 'shop', 'market', 'mall', 'retail', 'supermarket', 'grocery',
            'boutique', 'emporium', 'mart', 'bazaar'];
        $beautyKeywords = ['salon', 'beauty', 'spa', 'parlor', 'hair', 'nail', 'cosmetic'];
        $autoKeywords = ['auto', 'car', 'vehicle', 'garage', 'mechanic', 'petrol', 'fuel', 'tyre'];
        $techKeywords = ['electronics', 'computer', 'mobile', 'phone', 'repair', 'internet', 'cyber'];
        $sportKeywords = ['gym', 'fitness', 'sports', 'stadium', 'ground', 'playground', 'yoga'];
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
                    $match = collect($categories)->first(fn($n) => strtolower($n) === $targetCat);
                    if ($match) return $categories[$match];
                }
            }
        }

        // 3. Fuzzy: check if any existing category name is contained in the raw category
        foreach ($categories as $name => $catId) {
            if (str_contains($rawLower, strtolower($name))) {
                return $catId;
            }
        }

        // 4. No match — create new category
        $slug = \Illuminate\Support\Str::slug($rawCategory);
        $cat = \App\Models\Category::firstOrCreate(
            ['name' => $rawCategory, 'slug' => $slug],
            ['description' => 'Auto-created from import']
        );
        return $cat->id;
    }
}
