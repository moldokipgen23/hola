<?php

namespace Tests\Unit;

use App\Services\BusinessHours;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class BusinessHoursTest extends TestCase
{
    public function test_valid_schedule_normalizes_and_fills_missing_days(): void
    {
        $result = BusinessHours::validateSchedule([
            'mon' => '09:00-17:00',
            'sun' => 'CLOSED',
        ]);

        $this->assertTrue($result['valid']);
        $this->assertCount(7, $result['hours']);
        $this->assertSame([['09:00', '17:00']], $result['hours']['mon']);
        $this->assertSame('CLOSED', $result['hours']['sun']);
        $this->assertSame('CLOSED', $result['hours']['tue']);
    }

    public function test_invalid_range_is_rejected(): void
    {
        $result = BusinessHours::validateSchedule(['mon' => '09:00-25:00']);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('mon', $result['errors']);
    }

    public function test_unknown_day_is_rejected(): void
    {
        $result = BusinessHours::validateSchedule(['monday' => '09:00-17:00']);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('monday', $result['errors']);
    }

    public function test_multiple_ranges_in_one_day(): void
    {
        $result = BusinessHours::validateSchedule(['wed' => '09:00-13:00, 14:00-18:00']);

        $this->assertTrue($result['valid']);
        $this->assertCount(2, $result['hours']['wed']);
    }

    public function test_is_open_at_respects_day_and_range(): void
    {
        $hours = ['mon' => '09:00-17:00'];

        $this->assertTrue(BusinessHours::isOpenAt($hours, Carbon::parse('2026-08-03 10:00:00')));
        $this->assertFalse(BusinessHours::isOpenAt($hours, Carbon::parse('2026-08-03 18:00:00')));

        $sunday = Carbon::parse('2026-08-09 12:00:00');
        $this->assertFalse(BusinessHours::isOpenAt($hours, $sunday));
        $this->assertTrue(BusinessHours::closedToday($hours, $sunday));
    }

    public function test_closed_on_missing_day(): void
    {
        $hours = ['mon' => '09:00-17:00'];
        $sunday = Carbon::parse('2026-08-09 12:00:00');

        $this->assertFalse(BusinessHours::isOpenOnDate($hours, $sunday));
        $this->assertTrue(BusinessHours::isOpenOnDate($hours, Carbon::parse('2026-08-03')));
    }

    public function test_humanize_and_label(): void
    {
        $hours = ['mon' => '09:00-17:00', 'sun' => 'CLOSED'];

        $this->assertSame('9:00 AM – 5:00 PM', BusinessHours::labelForDay($hours, 'mon'));
        $this->assertSame('Closed', BusinessHours::labelForDay($hours, 'sun'));
        $this->assertArrayHasKey('mon', BusinessHours::humanize($hours));
    }

    public function test_null_or_empty_is_valid_and_returns_null(): void
    {
        $result = BusinessHours::validateSchedule(null);
        $this->assertTrue($result['valid']);
        $this->assertNull($result['hours']);
    }
}
