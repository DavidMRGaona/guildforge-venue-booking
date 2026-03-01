<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Unit\Domain\ValueObjects;

use InvalidArgumentException;
use Modules\VenueBookings\Domain\ValueObjects\DaySchedule;
use Tests\TestCase;

final class DayScheduleTest extends TestCase
{
    public function test_it_creates_with_valid_data(): void
    {
        $schedule = new DaySchedule(
            dayOfWeek: 1,
            openTime: '09:00',
            closeTime: '17:00',
            isEnabled: true,
        );

        $this->assertEquals(1, $schedule->dayOfWeek);
        $this->assertEquals('09:00', $schedule->openTime);
        $this->assertEquals('17:00', $schedule->closeTime);
        $this->assertTrue($schedule->isEnabled);
    }

    public function test_it_throws_on_invalid_day_of_week(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DaySchedule(
            dayOfWeek: 7,
            openTime: '09:00',
            closeTime: '17:00',
            isEnabled: true,
        );
    }

    public function test_it_throws_on_negative_day_of_week(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DaySchedule(
            dayOfWeek: -1,
            openTime: '09:00',
            closeTime: '17:00',
            isEnabled: true,
        );
    }

    public function test_it_allows_disabled_schedule_with_any_times(): void
    {
        $schedule = new DaySchedule(
            dayOfWeek: 0,
            openTime: '17:00',
            closeTime: '09:00',
            isEnabled: false,
        );

        $this->assertFalse($schedule->isEnabled);
        $this->assertEquals('17:00', $schedule->openTime);
        $this->assertEquals('09:00', $schedule->closeTime);
    }

    public function test_it_allows_cross_midnight_schedule_when_enabled(): void
    {
        $schedule = new DaySchedule(
            dayOfWeek: 5,
            openTime: '22:00',
            closeTime: '02:00',
            isEnabled: true,
        );

        $this->assertTrue($schedule->crossesMidnight());
        $this->assertEquals('22:00', $schedule->openTime);
        $this->assertEquals('02:00', $schedule->closeTime);
    }

    public function test_it_does_not_cross_midnight_for_normal_schedule(): void
    {
        $schedule = new DaySchedule(
            dayOfWeek: 1,
            openTime: '09:00',
            closeTime: '17:00',
            isEnabled: true,
        );

        $this->assertFalse($schedule->crossesMidnight());
    }

    public function test_it_throws_on_equal_open_and_close_when_enabled(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DaySchedule(
            dayOfWeek: 1,
            openTime: '10:00',
            closeTime: '10:00',
            isEnabled: true,
        );
    }

    public function test_it_serializes_to_array(): void
    {
        $schedule = new DaySchedule(
            dayOfWeek: 3,
            openTime: '10:00',
            closeTime: '18:00',
            isEnabled: true,
        );

        $array = $schedule->toArray();

        $this->assertEquals([
            'day_of_week' => 3,
            'open_time' => '10:00',
            'close_time' => '18:00',
            'is_enabled' => true,
            'label' => null,
        ], $array);
    }

    public function test_it_creates_from_array(): void
    {
        $schedule = DaySchedule::fromArray([
            'day_of_week' => 5,
            'open_time' => '08:00',
            'close_time' => '22:00',
            'is_enabled' => true,
        ]);

        $this->assertEquals(5, $schedule->dayOfWeek);
        $this->assertEquals('08:00', $schedule->openTime);
        $this->assertEquals('22:00', $schedule->closeTime);
        $this->assertTrue($schedule->isEnabled);
    }

    public function test_it_creates_day_schedule_with_label(): void
    {
        $schedule = new DaySchedule(
            dayOfWeek: 1,
            openTime: '10:00',
            closeTime: '14:00',
            isEnabled: true,
            label: 'Mañana',
        );

        $this->assertEquals('Mañana', $schedule->label);
    }

    public function test_label_defaults_to_null(): void
    {
        $schedule = new DaySchedule(
            dayOfWeek: 1,
            openTime: '10:00',
            closeTime: '14:00',
            isEnabled: true,
        );

        $this->assertNull($schedule->label);
    }

    public function test_to_array_includes_label(): void
    {
        $schedule = new DaySchedule(
            dayOfWeek: 1,
            openTime: '10:00',
            closeTime: '14:00',
            isEnabled: true,
            label: 'Mañana',
        );

        $array = $schedule->toArray();

        $this->assertEquals([
            'day_of_week' => 1,
            'open_time' => '10:00',
            'close_time' => '14:00',
            'is_enabled' => true,
            'label' => 'Mañana',
        ], $array);
    }

    public function test_from_array_reads_label(): void
    {
        $schedule = DaySchedule::fromArray([
            'day_of_week' => 1,
            'open_time' => '10:00',
            'close_time' => '14:00',
            'is_enabled' => true,
            'label' => 'Tarde',
        ]);

        $this->assertEquals('Tarde', $schedule->label);
    }

    public function test_from_array_handles_missing_label(): void
    {
        $schedule = DaySchedule::fromArray([
            'day_of_week' => 1,
            'open_time' => '10:00',
            'close_time' => '14:00',
            'is_enabled' => true,
        ]);

        $this->assertNull($schedule->label);
    }
}
