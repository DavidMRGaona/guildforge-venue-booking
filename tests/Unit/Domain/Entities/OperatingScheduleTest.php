<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Unit\Domain\Entities;

use Modules\VenueBookings\Domain\Entities\OperatingSchedule;
use Modules\VenueBookings\Domain\Enums\SchedulingMode;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Modules\VenueBookings\Domain\ValueObjects\BookingSlot;
use Modules\VenueBookings\Domain\ValueObjects\DaySchedule;
use Modules\VenueBookings\Domain\ValueObjects\OperatingScheduleId;
use Modules\VenueBookings\Domain\ValueObjects\TimeRange;
use Tests\TestCase;

final class OperatingScheduleTest extends TestCase
{
    public function test_it_creates_with_defaults(): void
    {
        $id = OperatingScheduleId::generate();
        $resourceId = BookableResourceId::generate();

        $schedule = new OperatingSchedule(
            id: $id,
            resourceId: $resourceId,
        );

        $this->assertSame($id, $schedule->id);
        $this->assertSame($resourceId, $schedule->resourceId);
        $this->assertEquals(60, $schedule->slotDurationMinutes);
        $this->assertEquals(1, $schedule->minConsecutiveSlots);
        $this->assertEquals(4, $schedule->maxConsecutiveSlots);
        $this->assertEmpty($schedule->daySchedules);
    }

    public function test_it_checks_operating_day(): void
    {
        $schedule = $this->createScheduleWithDays([
            new DaySchedule(dayOfWeek: 1, openTime: '09:00', closeTime: '17:00', isEnabled: true),
            new DaySchedule(dayOfWeek: 3, openTime: '10:00', closeTime: '18:00', isEnabled: true),
        ]);

        $this->assertTrue($schedule->isOperatingOn(1));
        $this->assertTrue($schedule->isOperatingOn(3));
    }

    public function test_it_returns_false_for_non_operating_day(): void
    {
        $schedule = $this->createScheduleWithDays([
            new DaySchedule(dayOfWeek: 1, openTime: '09:00', closeTime: '17:00', isEnabled: true),
            new DaySchedule(dayOfWeek: 2, openTime: '09:00', closeTime: '17:00', isEnabled: false),
        ]);

        // Day 4 has no schedule at all
        $this->assertFalse($schedule->isOperatingOn(4));

        // Day 2 has a schedule but is disabled
        $this->assertFalse($schedule->isOperatingOn(2));
    }

    public function test_it_gets_day_schedule(): void
    {
        $monday = new DaySchedule(dayOfWeek: 1, openTime: '09:00', closeTime: '17:00', isEnabled: true);
        $schedule = $this->createScheduleWithDays([$monday]);

        $daySchedule = $schedule->getDaySchedule(1);

        $this->assertNotNull($daySchedule);
        $this->assertEquals(1, $daySchedule->dayOfWeek);
        $this->assertEquals('09:00', $daySchedule->openTime);
        $this->assertEquals('17:00', $daySchedule->closeTime);
    }

    public function test_it_returns_null_for_missing_day_schedule(): void
    {
        $schedule = $this->createScheduleWithDays([
            new DaySchedule(dayOfWeek: 1, openTime: '09:00', closeTime: '17:00', isEnabled: true),
        ]);

        $this->assertNull($schedule->getDaySchedule(5));
    }

    public function test_it_generates_slots_for_date(): void
    {
        // Monday = dayOfWeek 1, 2026-03-02 is a Monday
        $schedule = $this->createScheduleWithDays(
            daySchedules: [
                new DaySchedule(dayOfWeek: 1, openTime: '09:00', closeTime: '17:00', isEnabled: true),
            ],
            slotDurationMinutes: 60,
        );

        $slots = $schedule->generateSlotsForDate('2026-03-02');

        // 8 hours / 60min = 8 slots
        $this->assertCount(8, $slots);
        $this->assertContainsOnlyInstancesOf(BookingSlot::class, $slots);

        // First slot: 09:00-10:00
        $this->assertEquals('09:00', $slots[0]->startTime);
        $this->assertEquals('10:00', $slots[0]->endTime);
        $this->assertTrue($slots[0]->isAvailable);

        // Last slot: 16:00-17:00
        $this->assertEquals('16:00', $slots[7]->startTime);
        $this->assertEquals('17:00', $slots[7]->endTime);
        $this->assertTrue($slots[7]->isAvailable);
    }

    public function test_it_generates_no_slots_for_disabled_day(): void
    {
        // 2026-03-03 is a Tuesday (dayOfWeek 2)
        $schedule = $this->createScheduleWithDays([
            new DaySchedule(dayOfWeek: 2, openTime: '09:00', closeTime: '17:00', isEnabled: false),
        ]);

        $slots = $schedule->generateSlotsForDate('2026-03-03');

        $this->assertEmpty($slots);
    }

    public function test_it_generates_slots_with_custom_duration(): void
    {
        // Monday = dayOfWeek 1, 2026-03-02 is a Monday
        $schedule = $this->createScheduleWithDays(
            daySchedules: [
                new DaySchedule(dayOfWeek: 1, openTime: '09:00', closeTime: '17:00', isEnabled: true),
            ],
            slotDurationMinutes: 30,
        );

        $slots = $schedule->generateSlotsForDate('2026-03-02');

        // 8 hours / 30min = 16 slots
        $this->assertCount(16, $slots);

        // First slot: 09:00-09:30
        $this->assertEquals('09:00', $slots[0]->startTime);
        $this->assertEquals('09:30', $slots[0]->endTime);

        // Second slot: 09:30-10:00
        $this->assertEquals('09:30', $slots[1]->startTime);
        $this->assertEquals('10:00', $slots[1]->endTime);
    }

    public function test_it_generates_slots_for_cross_midnight_schedule(): void
    {
        // 2026-03-06 is a Friday (dayOfWeek 5)
        $schedule = $this->createScheduleWithDays(
            daySchedules: [
                new DaySchedule(dayOfWeek: 5, openTime: '22:00', closeTime: '02:00', isEnabled: true),
            ],
            slotDurationMinutes: 60,
        );

        $slots = $schedule->generateSlotsForDate('2026-03-06');

        // 4 hours / 60min = 4 slots
        $this->assertCount(4, $slots);

        // Slots: 22:00-23:00, 23:00-00:00, 00:00-01:00, 01:00-02:00
        $this->assertEquals('22:00', $slots[0]->startTime);
        $this->assertEquals('23:00', $slots[0]->endTime);

        $this->assertEquals('23:00', $slots[1]->startTime);
        $this->assertEquals('00:00', $slots[1]->endTime);

        $this->assertEquals('00:00', $slots[2]->startTime);
        $this->assertEquals('01:00', $slots[2]->endTime);

        $this->assertEquals('01:00', $slots[3]->startTime);
        $this->assertEquals('02:00', $slots[3]->endTime);
    }

    public function test_it_generates_block_slots_for_date(): void
    {
        // Monday = dayOfWeek 1, 2026-03-02 is a Monday
        $schedule = $this->createScheduleWithDays(
            daySchedules: [
                new DaySchedule(dayOfWeek: 1, openTime: '10:00', closeTime: '14:00', isEnabled: true, label: 'Mañana'),
                new DaySchedule(dayOfWeek: 1, openTime: '16:00', closeTime: '20:00', isEnabled: true, label: 'Tarde'),
            ],
            slotDurationMinutes: 60,
            schedulingMode: SchedulingMode::TimeBlocks,
        );

        $slots = $schedule->generateSlotsForDate('2026-03-02');

        $this->assertCount(2, $slots);
        $this->assertContainsOnlyInstancesOf(BookingSlot::class, $slots);

        // First block: Mañana 10:00-14:00
        $this->assertEquals('10:00', $slots[0]->startTime);
        $this->assertEquals('14:00', $slots[0]->endTime);
        $this->assertTrue($slots[0]->isAvailable);
        $this->assertEquals('Mañana', $slots[0]->label);

        // Second block: Tarde 16:00-20:00
        $this->assertEquals('16:00', $slots[1]->startTime);
        $this->assertEquals('20:00', $slots[1]->endTime);
        $this->assertTrue($slots[1]->isAvailable);
        $this->assertEquals('Tarde', $slots[1]->label);
    }

    public function test_block_mode_ignores_slot_duration(): void
    {
        // Even with 30min slot duration, block mode returns whole blocks
        $schedule = $this->createScheduleWithDays(
            daySchedules: [
                new DaySchedule(dayOfWeek: 1, openTime: '10:00', closeTime: '14:00', isEnabled: true, label: 'Mañana'),
            ],
            slotDurationMinutes: 30,
            schedulingMode: SchedulingMode::TimeBlocks,
        );

        $slots = $schedule->generateSlotsForDate('2026-03-02');

        $this->assertCount(1, $slots);
        $this->assertEquals('10:00', $slots[0]->startTime);
        $this->assertEquals('14:00', $slots[0]->endTime);
        $this->assertEquals('Mañana', $slots[0]->label);
    }

    public function test_time_slots_mode_unchanged(): void
    {
        // Explicitly set TimeSlots mode — existing behavior preserved, no labels
        $schedule = $this->createScheduleWithDays(
            daySchedules: [
                new DaySchedule(dayOfWeek: 1, openTime: '09:00', closeTime: '11:00', isEnabled: true),
            ],
            slotDurationMinutes: 60,
            schedulingMode: SchedulingMode::TimeSlots,
        );

        $slots = $schedule->generateSlotsForDate('2026-03-02');

        $this->assertCount(2, $slots);
        $this->assertEquals('09:00', $slots[0]->startTime);
        $this->assertEquals('10:00', $slots[0]->endTime);
        $this->assertNull($slots[0]->label);
    }

    public function test_block_mode_cross_midnight(): void
    {
        // 2026-03-06 is a Friday (dayOfWeek 5)
        $schedule = $this->createScheduleWithDays(
            daySchedules: [
                new DaySchedule(dayOfWeek: 5, openTime: '22:00', closeTime: '02:00', isEnabled: true, label: 'Noche'),
            ],
            slotDurationMinutes: 60,
            schedulingMode: SchedulingMode::TimeBlocks,
        );

        $slots = $schedule->generateSlotsForDate('2026-03-06');

        $this->assertCount(1, $slots);
        $this->assertEquals('22:00', $slots[0]->startTime);
        $this->assertEquals('02:00', $slots[0]->endTime);
        $this->assertEquals('Noche', $slots[0]->label);
        $this->assertTrue($slots[0]->isAvailable);
    }

    public function test_block_mode_skips_disabled_entries(): void
    {
        $schedule = $this->createScheduleWithDays(
            daySchedules: [
                new DaySchedule(dayOfWeek: 1, openTime: '10:00', closeTime: '14:00', isEnabled: true, label: 'Mañana'),
                new DaySchedule(dayOfWeek: 1, openTime: '16:00', closeTime: '20:00', isEnabled: false, label: 'Tarde'),
            ],
            slotDurationMinutes: 60,
            schedulingMode: SchedulingMode::TimeBlocks,
        );

        $slots = $schedule->generateSlotsForDate('2026-03-02');

        $this->assertCount(1, $slots);
        $this->assertEquals('Mañana', $slots[0]->label);
    }

    public function test_it_generates_slots_for_full_day_schedule(): void
    {
        // 2026-03-02 is a Monday (dayOfWeek 1)
        // 00:00–23:59 with 60-min slots should produce 24 slots (00:00–01:00 … 23:00–00:00)
        $schedule = $this->createScheduleWithDays(
            daySchedules: [
                new DaySchedule(dayOfWeek: 1, openTime: '00:00', closeTime: '23:59', isEnabled: true),
            ],
            slotDurationMinutes: 60,
        );

        $slots = $schedule->generateSlotsForDate('2026-03-02');

        $this->assertCount(24, $slots);

        // First slot: 00:00-01:00
        $this->assertEquals('00:00', $slots[0]->startTime);
        $this->assertEquals('01:00', $slots[0]->endTime);

        // Last slot: 23:00-00:00
        $this->assertEquals('23:00', $slots[23]->startTime);
        $this->assertEquals('00:00', $slots[23]->endTime);
    }

    public function test_scheduling_mode_defaults_to_time_slots(): void
    {
        $schedule = new OperatingSchedule(
            id: OperatingScheduleId::generate(),
            resourceId: BookableResourceId::generate(),
        );

        $this->assertEquals(SchedulingMode::TimeSlots, $schedule->schedulingMode);
    }

    public function test_slots_do_not_depend_on_the_php_default_timezone(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('Pacific/Kiritimati');

        try {
            // 2026-03-29 is a Sunday (dayOfWeek 0) and the day Madrid moves its clocks forward
            $schedule = $this->createScheduleWithDays([
                new DaySchedule(dayOfWeek: 0, openTime: '01:00', closeTime: '04:00', isEnabled: true),
            ]);

            $slots = $schedule->generateSlotsForDate('2026-03-29');

            $this->assertSame(['01:00', '02:00', '03:00'], array_map(fn (BookingSlot $s): string => $s->startTime, $slots));
            $this->assertSame(['02:00', '03:00', '04:00'], array_map(fn (BookingSlot $s): string => $s->endTime, $slots));
        } finally {
            date_default_timezone_set($previous);
        }
    }

    public function test_the_last_slot_may_overrun_the_closing_time_as_before(): void
    {
        $schedule = $this->createScheduleWithDays(
            daySchedules: [new DaySchedule(dayOfWeek: 1, openTime: '09:00', closeTime: '11:00', isEnabled: true)],
            slotDurationMinutes: 45,
        );

        $slots = $schedule->generateSlotsForDate('2026-03-02');

        $this->assertSame(['09:00', '09:45', '10:30'], array_map(fn (BookingSlot $s): string => $s->startTime, $slots));
        $this->assertSame('11:15', $slots[2]->endTime);
    }

    public function test_schedule_times_with_seconds_give_hh_mm_slots(): void
    {
        $schedule = $this->createScheduleWithDays([
            new DaySchedule(dayOfWeek: 1, openTime: '10:00:00', closeTime: '12:00:00', isEnabled: true),
        ]);

        $slots = $schedule->generateSlotsForDate('2026-03-02');

        $this->assertSame('10:00', $slots[0]->startTime);
        $this->assertSame('11:00', $slots[0]->endTime);
        $this->assertSame('12:00', $slots[1]->endTime);
    }

    public function test_an_unreadable_date_has_no_slots(): void
    {
        $schedule = $this->createScheduleWithDays([
            new DaySchedule(dayOfWeek: 1, openTime: '10:00', closeTime: '12:00', isEnabled: true),
        ]);

        $this->assertSame([], $schedule->generateSlotsForDate('not-a-date'));
    }

    public function test_a_range_made_of_consecutive_slots_counts_them(): void
    {
        $schedule = $this->createScheduleWithDays([
            new DaySchedule(dayOfWeek: 1, openTime: '10:00', closeTime: '14:00', isEnabled: true),
        ]);

        $this->assertSame(1, $schedule->slotsCoveredBy('2026-03-02', new TimeRange('10:00', '11:00')));
        $this->assertSame(2, $schedule->slotsCoveredBy('2026-03-02', new TimeRange('11:00', '13:00')));
        $this->assertSame(4, $schedule->slotsCoveredBy('2026-03-02', new TimeRange('10:00', '14:00')));
    }

    public function test_a_range_off_the_slot_boundaries_or_outside_opening_hours_is_not_covered(): void
    {
        $schedule = $this->createScheduleWithDays([
            new DaySchedule(dayOfWeek: 1, openTime: '10:00', closeTime: '14:00', isEnabled: true),
        ]);

        $this->assertNull($schedule->slotsCoveredBy('2026-03-02', new TimeRange('10:30', '11:30')));
        $this->assertNull($schedule->slotsCoveredBy('2026-03-02', new TimeRange('09:00', '11:00')));
        $this->assertNull($schedule->slotsCoveredBy('2026-03-02', new TimeRange('13:00', '15:00')));
        $this->assertNull($schedule->slotsCoveredBy('2026-03-02', new TimeRange('11:00', '10:00')));
    }

    public function test_a_closed_or_disabled_day_covers_nothing(): void
    {
        $schedule = $this->createScheduleWithDays([
            new DaySchedule(dayOfWeek: 1, openTime: '10:00', closeTime: '14:00', isEnabled: true),
            new DaySchedule(dayOfWeek: 2, openTime: '10:00', closeTime: '14:00', isEnabled: false),
        ]);

        // 2026-03-03 is a Tuesday (disabled), 2026-03-04 a Wednesday (no schedule)
        $this->assertNull($schedule->slotsCoveredBy('2026-03-03', new TimeRange('10:00', '11:00')));
        $this->assertNull($schedule->slotsCoveredBy('2026-03-04', new TimeRange('10:00', '11:00')));
    }

    public function test_thirty_minute_slots_are_covered_on_half_hours(): void
    {
        $schedule = $this->createScheduleWithDays(
            daySchedules: [new DaySchedule(dayOfWeek: 1, openTime: '10:00', closeTime: '14:00', isEnabled: true)],
            slotDurationMinutes: 30,
        );

        $this->assertSame(2, $schedule->slotsCoveredBy('2026-03-02', new TimeRange('10:30', '11:30')));
    }

    public function test_a_run_that_crosses_midnight_is_covered(): void
    {
        // 2026-03-06 is a Friday (dayOfWeek 5)
        $schedule = $this->createScheduleWithDays([
            new DaySchedule(dayOfWeek: 5, openTime: '22:00', closeTime: '02:00', isEnabled: true),
        ]);

        $this->assertSame(2, $schedule->slotsCoveredBy('2026-03-06', new TimeRange('23:00', '01:00')));
        $this->assertSame(2, $schedule->slotsCoveredBy('2026-03-06', new TimeRange('00:00', '02:00')));
        $this->assertNull($schedule->slotsCoveredBy('2026-03-06', new TimeRange('01:00', '03:00')));
    }

    public function test_the_last_slot_of_a_full_day_is_covered(): void
    {
        $schedule = $this->createScheduleWithDays([
            new DaySchedule(dayOfWeek: 1, openTime: '00:00', closeTime: '23:59', isEnabled: true),
        ]);

        $this->assertSame(1, $schedule->slotsCoveredBy('2026-03-02', new TimeRange('23:00', '00:00')));
    }

    public function test_a_block_must_be_booked_whole_and_alone(): void
    {
        $schedule = $this->createScheduleWithDays(
            daySchedules: [
                new DaySchedule(dayOfWeek: 1, openTime: '10:00', closeTime: '14:00', isEnabled: true, label: 'Mañana'),
                new DaySchedule(dayOfWeek: 1, openTime: '14:00', closeTime: '18:00', isEnabled: true, label: 'Tarde'),
            ],
            schedulingMode: SchedulingMode::TimeBlocks,
        );

        $this->assertSame(1, $schedule->slotsCoveredBy('2026-03-02', new TimeRange('10:00', '14:00')));
        $this->assertSame(1, $schedule->slotsCoveredBy('2026-03-02', new TimeRange('14:00', '18:00')));
        $this->assertNull($schedule->slotsCoveredBy('2026-03-02', new TimeRange('10:00', '18:00')));
        $this->assertNull($schedule->slotsCoveredBy('2026-03-02', new TimeRange('10:00', '12:00')));
    }

    public function test_a_block_that_crosses_midnight_is_covered(): void
    {
        $schedule = $this->createScheduleWithDays(
            daySchedules: [new DaySchedule(dayOfWeek: 5, openTime: '22:00', closeTime: '02:00', isEnabled: true, label: 'Noche')],
            schedulingMode: SchedulingMode::TimeBlocks,
        );

        $this->assertSame(1, $schedule->slotsCoveredBy('2026-03-06', new TimeRange('22:00', '02:00')));
    }

    /**
     * @param  array<DaySchedule>  $daySchedules
     */
    private function createScheduleWithDays(
        array $daySchedules,
        int $slotDurationMinutes = 60,
        SchedulingMode $schedulingMode = SchedulingMode::TimeSlots,
    ): OperatingSchedule {
        return new OperatingSchedule(
            id: OperatingScheduleId::generate(),
            resourceId: BookableResourceId::generate(),
            slotDurationMinutes: $slotDurationMinutes,
            daySchedules: $daySchedules,
            schedulingMode: $schedulingMode,
        );
    }
}
