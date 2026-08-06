<?php

namespace App\Services;

use App\Models\FeatureFlag;
use Illuminate\Database\Eloquent\Collection;

class FeatureFlagService
{
    public function all(): Collection
    {
        return FeatureFlag::orderBy('group')->orderBy('name')->get();
    }

    public function enabled(): Collection
    {
        return FeatureFlag::enabled()->orderBy('name')->get();
    }

    public function isEnabled(string $key): bool
    {
        return FeatureFlag::isEnabled($key);
    }

    public function enable(string $key): FeatureFlag
    {
        $flag = FeatureFlag::where('key', $key)->firstOrFail();
        $flag->update(['is_enabled' => true]);

        return $flag->fresh();
    }

    public function disable(string $key): FeatureFlag
    {
        $flag = FeatureFlag::where('key', $key)->firstOrFail();
        $flag->update(['is_enabled' => false]);

        return $flag->fresh();
    }

    public function create(array $data): FeatureFlag
    {
        return FeatureFlag::create($data);
    }

    public function update(FeatureFlag $flag, array $data): FeatureFlag
    {
        $flag->update($data);

        return $flag->fresh();
    }

    public function delete(FeatureFlag $flag): bool
    {
        return $flag->delete();
    }
}
