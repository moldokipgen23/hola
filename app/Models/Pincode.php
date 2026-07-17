<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pincode extends Model
{
    protected $fillable = [
        'pincode',
        'locality',
        'district',
        'state',
        'latitude',
        'longitude',
        'serviceable',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'serviceable' => 'boolean',
    ];

    public function scopeSearch($query, $q)
    {
        return $query->where(function ($qry) use ($q) {
            $qry->where('pincode', 'like', "{$q}%")
                ->orWhere('locality', 'like', "%{$q}%")
                ->orWhere('district', 'like', "%{$q}%");
        });
    }

    public static function haversine($latitude, $longitude, $radius = 10)
    {
        $lat = (float) $latitude;
        $lng = (float) $longitude;

        return self::selectRaw(
            "*, (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance",
            [$lat, $lng, $lat]
        )->having('distance', '<=', $radius)
         ->orderBy('distance');
    }

    public static function isServiceable(string $pincode): bool
    {
        return self::where('pincode', $pincode)->where('serviceable', true)->exists();
    }

    public static function lookup(string $pincode): ?self
    {
        return self::where('pincode', $pincode)->first();
    }
}
