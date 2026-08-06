<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'state',
        'district',
        'pincode',
        'is_home',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_home' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
