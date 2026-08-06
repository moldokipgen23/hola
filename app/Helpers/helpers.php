<?php

use App\Models\Category;

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

        // Fallback: deterministic keyword classifier on name/types/address.
        // Guards against generic Google types like "establishment" ever being
        // used as a category again.
        if (! $category) {
            $category = classifyBusinessByKeywords(
                (string) ($data['name'] ?? ''),
                is_array($data['types'] ?? null) ? $data['types'] : [],
                (string) ($data['address'] ?? ''),
            );
        }

        if (! $category) {
            return null;
        }

        $subcategory = null;
        if (! empty($data['subcategory_id'])) {
            // Legacy subcategory ids are resolved through their migrated
            // level-2 child category (metadata.legacy_subcategory_id).
            $subcategory = Category::active()
                ->where('parent_id', $category->id)
                ->where('metadata->legacy_subcategory_id', $data['subcategory_id'])
                ->first();
        }

        if (! $subcategory && ! empty($data['subcategory_slug'])) {
            $subcategory = Category::active()
                ->where('parent_id', $category->id)
                ->where('slug', $data['subcategory_slug'])
                ->first();
        }

        return [
            'category_id' => $category->id,
            'subcategory_id' => $subcategory?->id,
        ];
    }
}

if (! function_exists('classifyBusinessByKeywords')) {
    /**
     * Deterministic keyword classifier for business names/types/address.
     *
     * Google's Places API returns generic types like "establishment" first,
     * so importers must NEVER treat the first type as the category. This maps
     * a business to the correct local category via ordered keyword rules
     * (most specific first) against name + types + address.
     *
     * Returns the matching Category or null when nothing confidently matches.
     */
    function classifyBusinessByKeywords(string $name, array $types = [], ?string $address = null): ?\App\Models\Category
    {
        $haystack = mb_strtolower(implode(' ', array_filter([
            $name,
            implode(' ', $types),
            $address ?? '',
        ])));

        // [category_slug, [keywords...]]. Matching is by LONGEST matched
        // keyword, so a specific term ("pharmacy") always beats a generic one
        // ("store") for the same business.
        $rules = [
            'football-turf' => ['football turf', 'turf ground', 'football ground', 'football field', 'astro turf', 'playfield', 'leisure turf', 'arena turf', 'sports turf', 'turf', 'football club', 'false 9'],
            'salons' => ['salon', 'saloon', 'unisex hair', 'hair studio', 'haircut', 'barber', 'hair and beauty'],
            'beauty-parlours' => ['beauty parlour', 'beauty parlor'],
            'beauty-wellness' => ['beauty spa', 'spa and', 'waxing', 'eyebrow', 'threading', 'nail art', 'skin care', 'makeover', 'bridal'],
            'pharmacies' => ['pharmacy', 'medical store', 'medical shop', 'drugstore', 'chemist', 'medico', 'medicine store'],
            'hospitals' => ['hospital'],
            'clinics' => ['clinic', 'dental', 'dentist', 'doctor', 'medical centre', 'medical center', 'healthcare centre', 'health centre', 'healthcare center', 'health institute', 'health institute'],
            'schools' => ['high school', 'public school', 'english school', 'playschool', 'school', 'academy', 'foundation school', 'residential school'],
            'colleges' => ['college', 'university'],
            'tuition-centers' => ['tuition', 'coaching', 'tutorial', 'learning centre', 'learning center', 'classes', 'study centre', 'study center', 'nios'],
            'music-school' => ['music school', 'music academy', 'singing class', 'guitar school'],
            'dance-school' => ['dance school', 'dance academy'],
            'electronics-tech' => ['mobile', 'electronics', 'computer', 'laptop', 'mobile repair', 'phone repair', 'cell phone', 'cctv', 'gadget', 'tronics'],
            'gyms' => ['gym', 'gymnasium', 'workout'],
            'sports-fitness' => ['sports complex', 'sports arena', 'badminton', 'basketball', 'boxing', 'martial arts', 'sports club', 'sports centre', 'sports center', 'swimming pool', 'arena'],
            'restaurants' => ['restaurant', 'eatery', 'diner', 'canteen', 'dhaba', 'food court', 'bhojanalya'],
            'cafes' => ['coffee shop', 'coffee house', 'cafe', 'pattisserie', 'ice cream parlour', 'ice cream parlor'],
            'food-restaurants' => ['fast food', 'bakery', 'sweet shop', 'tiffin', 'snack bar', 'food stall', 'biryani', 'momos', 'catering service'],
            'hotels' => ['hotel'],
            'guest-houses' => ['guest house', 'guesthouse', 'lodge', 'inn'],
            'homestays' => ['homestay', 'home stay', 'floating homestay'],
            'resorts' => ['resort'],
            'laundry-dry-cleaning' => ['laundry', 'dryclean', 'dry clean', 'dry-cleaning', 'dhobi', 'ironing'],
            'catering-food-service' => ['catering', 'banquet'],
            'taxi-services' => ['taxi', 'cab service', 'car rental', 'auto rental', 'travel agency'],
            'transport' => ['bus service', 'transport', 'logistics', 'courier', 'parcel service', 'truck'],
            'automobiles' => ['garage', 'mechanic', 'automobile', 'auto repair', 'car service', 'bike service', 'tyre', 'workshop', 'spare part', 'showroom', 'motor'],
            'home-local-services' => ['electrician', 'plumber', 'carpenter', 'painter', 'tailor', 'taylor', 'welding', 'welder', 'photographer', 'photographic', 'photoshop', 'photo studio', 'printing press', 'print press', 'xerox', 'repair shop', 'maintenance', 'pest control', 'interior', 'furniture', 'hardware'],
            'churches' => ['church', 'chapel', 'christian fellowship', 'congregation'],
            'post-offices' => ['post office', 'india post', 'speed post'],
            'fire-emergency-services' => ['fire station', 'fire brigade'],
            'government-public-services' => ['government', 'government office', 'secretariat', 'collectorate', 'tehsil', 'police station', 'police', 'court', 'bank', 'atm', 'municipal', 'electricity office', 'water supply', 'public service', 'panchayat', 'administrative', 'mspdcl', ' office'],
            'parks' => ['public park', 'city park', 'garden', 'park'],
            'religious-community-places' => ['temple', 'mosque', 'masjid', 'gurdwara', 'monastery', 'mission compound'],
            'general-store' => ['general store', 'grocery', 'provision', 'supermarket', 'department store', 'emporium', 'hardware store', 'electrical store', 'variety store', 'stationery', 'book store', 'sports shop', 'clothing store', 'garment', 'tailoring shop', 'bazaar', 'bazar', 'wholesale', 'distributor', 'supplier', 'vending', 'enterprise', 'trading'],
        ];

        $best = null;
        $bestLen = 0;
        foreach ($rules as $slug => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($haystack, $keyword) && mb_strlen($keyword) > $bestLen) {
                    $best = $slug;
                    $bestLen = mb_strlen($keyword);
                }
            }
        }

        if ($best === null) {
            return null;
        }

        // Resolve the category: prefer an active canonical category by slug,
        // falling back to any active category with that slug or name.
        $category = \App\Models\Category::active()
            ->where('is_canonical', true)
            ->where('slug', $best)
            ->first()
            ?? \App\Models\Category::active()
                ->where('slug', $best)
                ->first()
            ?? \App\Models\Category::active()
                ->where('name', \Illuminate\Support\Str::headline(str_replace('-', ' ', $best)))
                ->first();

        return $category;
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

        // Legacy subcategories now live as level-2 category children.
        $subcategories = Category::where('parent_id', $categoryId)
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
