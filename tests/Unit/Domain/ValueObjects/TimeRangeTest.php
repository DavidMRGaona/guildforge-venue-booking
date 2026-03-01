<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Unit\Domain\ValueObjects;

use InvalidArgumentException;
use Modules\VenueBookings\Domain\ValueObjects\TimeRange;
use Tests\TestCase;

final class TimeRangeTest extends TestCase
{
    public function test_it_creates_with_valid_times(): void
    {
        $timeRange = new TimeRange('10:00', '12:00');

        $this->assertEquals('10:00', $timeRange->startTime);
        $this->assertEquals('12:00', $timeRange->endTime);
    }

    public function test_it_allows_cross_midnight_time_range(): void
    {
        $timeRange = new TimeRange('22:00', '02:00');

        $this->assertEquals('22:00', $timeRange->startTime);
        $this->assertEquals('02:00', $timeRange->endTime);
        $this->assertTrue($timeRange->crossesMidnight());
    }

    public function test_it_does_not_cross_midnight_for_normal_range(): void
    {
        $timeRange = new TimeRange('10:00', '14:00');

        $this->assertFalse($timeRange->crossesMidnight());
    }

    public function test_it_detects_overlapping_cross_midnight_ranges(): void
    {
        $range1 = new TimeRange('22:00', '02:00');
        $range2 = new TimeRange('01:00', '03:00');

        $this->assertTrue($range1->overlaps($range2));
        $this->assertTrue($range2->overlaps($range1));
    }

    public function test_cross_midnight_range_does_not_overlap_with_daytime(): void
    {
        $range1 = new TimeRange('22:00', '02:00');
        $range2 = new TimeRange('10:00', '14:00');

        $this->assertFalse($range1->overlaps($range2));
        $this->assertFalse($range2->overlaps($range1));
    }

    public function test_it_calculates_duration_for_cross_midnight(): void
    {
        $timeRange = new TimeRange('22:00', '02:00');

        $this->assertEquals(240, $timeRange->durationMinutes());
    }

    public function test_it_checks_containment_for_cross_midnight(): void
    {
        $timeRange = new TimeRange('22:00', '02:00');

        $this->assertTrue($timeRange->contains('23:00'));
        $this->assertTrue($timeRange->contains('01:00'));
        $this->assertFalse($timeRange->contains('10:00'));
    }

    public function test_it_throws_on_equal_start_and_end(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TimeRange('10:00', '10:00');
    }

    public function test_it_calculates_duration_minutes(): void
    {
        $timeRange = new TimeRange('10:00', '12:00');

        $this->assertEquals(120, $timeRange->durationMinutes());
    }

    public function test_it_detects_overlapping_ranges(): void
    {
        $range1 = new TimeRange('10:00', '12:00');
        $range2 = new TimeRange('11:00', '13:00');

        $this->assertTrue($range1->overlaps($range2));
        $this->assertTrue($range2->overlaps($range1));
    }

    public function test_it_detects_non_overlapping_ranges(): void
    {
        $range1 = new TimeRange('10:00', '12:00');
        $range2 = new TimeRange('13:00', '15:00');

        $this->assertFalse($range1->overlaps($range2));
        $this->assertFalse($range2->overlaps($range1));
    }

    public function test_it_detects_adjacent_ranges_dont_overlap(): void
    {
        $range1 = new TimeRange('10:00', '12:00');
        $range2 = new TimeRange('12:00', '14:00');

        $this->assertFalse($range1->overlaps($range2));
        $this->assertFalse($range2->overlaps($range1));
    }

    public function test_it_checks_time_containment(): void
    {
        $timeRange = new TimeRange('10:00', '12:00');

        $this->assertTrue($timeRange->contains('11:00'));
        $this->assertFalse($timeRange->contains('13:00'));
    }

    public function test_it_serializes_to_array(): void
    {
        $timeRange = new TimeRange('10:00', '12:00');

        $array = $timeRange->toArray();

        $this->assertEquals([
            'start_time' => '10:00',
            'end_time' => '12:00',
        ], $array);
    }

    public function test_it_creates_from_array(): void
    {
        $timeRange = TimeRange::fromArray([
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $this->assertEquals('09:00', $timeRange->startTime);
        $this->assertEquals('17:00', $timeRange->endTime);
    }
}
