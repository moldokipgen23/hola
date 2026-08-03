<?php

namespace App\Observers;

use Illuminate\Support\Facades\Log;

class SubcategoryObserver
{
    public function creating($subcategory): void
    {
        Log::warning('Subcategory creation is deprecated. Use hierarchical categories with parent_id instead.', [
            'name' => $subcategory->name ?? null,
            'category_id' => $subcategory->category_id ?? null,
        ]);
    }
}
