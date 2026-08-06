<?php

namespace App\Rules;

use App\Services\BusinessHours;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class WorkingHours implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === []) {
            return;
        }

        if (! is_array($value)) {
            $fail('Working hours must be an array keyed by day.');

            return;
        }

        $result = BusinessHours::validateSchedule($value);

        if (! $result['valid']) {
            foreach ($result['errors'] as $day => $error) {
                $fail($error);
            }
        }
    }
}
