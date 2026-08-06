<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class BusinessHours
{
    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /**
     * Validate an array of per-day working hours and return a normalized version.
     *
     * Accepted shape (JSON column on businesses):
     *   ['mon' => '10:00-22:00', 'wed' => '09:00-13:00, 14:00-18:00', 'sun' => 'CLOSED']
     * A day may be omitted (treated as closed), 'CLOSED'/'closed' (closed), or a
     * 24h marker ('24h'/'' for 00:00-23:59).
     *
     * Returns ['valid' => true, 'hours' => normalized, 'errors' => []] or
     * ['valid' => false, 'errors' => ['day' => 'message', ...]].
     */
    public static function validateSchedule(?array $workingHours): array
    {
        if ($workingHours === null || $workingHours === []) {
            return ['valid' => true, 'hours' => null, 'errors' => []];
        }

        $normalized = [];
        $errors = [];

        foreach ($workingHours as $day => $value) {
            $day = strtolower(trim((string) $day));

            if (! in_array($day, self::DAYS, true)) {
                $errors[$day] = "Unknown day '{$day}'. Use ".implode('/', self::DAYS).'.';

                continue;
            }

            if (empty($value) || strtoupper((string) $value) === 'CLOSED') {
                $normalized[$day] = 'CLOSED';

                continue;
            }

            $parts = array_filter(array_map('trim', explode(',', (string) $value)));

            if ($parts === []) {
                $normalized[$day] = 'CLOSED';

                continue;
            }

            $ranges = [];
            foreach ($parts as $range) {
                if (! preg_match('/^([0-9]{1,2}):([0-9]{2})\s*-\s*([0-9]{1,2}):([0-9]{2})$/i', $range, $m)) {
                    $errors[$day] = "Invalid range '{$range}' on {$day}. Use HH:MM-HH:MM.";

                    continue 2;
                }

                $open = $m[1].':'.$m[2];
                $close = $m[3].':'.$m[4];

                if (intval($m[1]) > 23 || intval($m[2]) > 59 || intval($m[3]) > 23 || intval($m[4]) > 59) {
                    $errors[$day] = "Invalid time on {$day}. Hours are 00:00–23:59.";

                    continue 2;
                }

                // Normalize to "HH:MM" (leading zero) for consistent storage.
                $ranges[] = [self::pad($open), self::pad($close)];
            }

            $normalized[$day] = $ranges;
        }

        if ($errors !== []) {
            return ['valid' => false, 'hours' => null, 'errors' => $errors];
        }

        // Fill any omitted days as closed so consumers can rely on all 7 present.
        foreach (self::DAYS as $day) {
            if (! isset($normalized[$day])) {
                $normalized[$day] = 'CLOSED';
            }
        }

        return ['valid' => true, 'hours' => $normalized, 'errors' => []];
    }

    /**
     * Whether the business's working hours have it open at the given time.
     */
    public static function isOpenAt(?array $workingHours, ?CarbonInterface $at = null): bool
    {
        $at = $at ?: Carbon::now();
        $result = self::validateSchedule($workingHours);

        if (! $result['valid'] || ! $result['hours']) {
            return false;
        }

        $dayHours = $result['hours'][strtolower($at->format('D'))] ?? 'CLOSED';

        if ($dayHours === 'CLOSED' || $dayHours === null) {
            return false;
        }

        $minutes = $at->hour * 60 + $at->minute;

        foreach ($dayHours as [$open, $close]) {
            $openMin = self::toMinutes($open);
            $closeMin = self::toMinutes($close);

            // 24h day (open === close) is always open.
            if ($openMin === $closeMin) {
                return true;
            }

            if ($minutes >= $openMin && $minutes < $closeMin) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the business is closed on the calendar date of $at
     * (i.e., it has no open ranges that day).
     */
    public static function closedToday(?array $workingHours, ?CarbonInterface $at = null): bool
    {
        return ! self::isOpenOnDate($workingHours, $at ?: Carbon::now());
    }

    /**
     * Whether the business has at least one open range on the given calendar date.
     */
    public static function isOpenOnDate(?array $workingHours, CarbonInterface $date): bool
    {
        $result = self::validateSchedule($workingHours);

        if (! $result['valid'] || ! $result['hours']) {
            return false;
        }

        $dayHours = $result['hours'][strtolower($date->format('D'))] ?? 'CLOSED';

        return $dayHours !== 'CLOSED' && $dayHours !== null && $dayHours !== [];
    }

    /**
     * Human-readable label for a single configured day (e.g. "10:00 AM – 10:00 PM").
     */
    public static function labelForDay(?array $workingHours, string $day): string
    {
        $result = self::validateSchedule($workingHours);
        $value = $result['hours'][strtolower($day)] ?? 'CLOSED';

        if ($value === 'CLOSED' || $value === null) {
            return 'Closed';
        }

        return collect($value)
            ->map(fn (array $range): string => self::rangeLabel($range[0], $range[1]))
            ->implode(', ');
    }

    public static function humanize(?array $workingHours): array
    {
        $result = self::validateSchedule($workingHours);

        if (! $result['valid'] || ! $result['hours']) {
            return [];
        }

        $out = [];
        foreach (self::DAYS as $day) {
            $value = $result['hours'][$day] ?? 'CLOSED';
            $out[$day] = $value === 'CLOSED' ? 'Closed' : collect($value)
                ->map(fn ($range) => self::rangeLabel($range[0], $range[1]))
                ->implode(', ');
        }

        return $out;
    }

    private static function rangeLabel(string $open, string $close): string
    {
        if ($open === '00:00' && $close === '24:00') {
            return 'Open 24 hours';
        }

        return self::timeLabel($open).' – '.self::timeLabel($close);
    }

    private static function timeLabel(string $hhmm): string
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));
        $amPm = $h >= 12 ? 'PM' : 'AM';
        $h12 = $h % 12;
        $h12 = $h12 === 0 ? 12 : $h12;

        return sprintf('%d:%02d %s', $h12, $m, $amPm);
    }

    private static function pad(string $hhmm): string
    {
        if ($hhmm === '24:00') {
            return '24:00';
        }

        [$h, $m] = array_map('intval', explode(':', $hhmm));

        return sprintf('%02d:%02d', $h, $m);
    }

    private static function toMinutes(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));

        return $h * 60 + $m;
    }
}
