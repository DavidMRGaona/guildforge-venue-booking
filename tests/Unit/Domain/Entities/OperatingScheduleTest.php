<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Unit\Domain\Entities;

use Modules\VenueBookings\Domain\Entities\OperatingSchedule;
use Modules\VenueBookings\Domain\Enums\SchedulingMode;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Modules\VenueBookings\Domain\ValueObjects\BookingSlot;
use Modules\VenueBookings\Domain\ValueObjects\DaySchedule;
use Modules\VenueBookings\Domain\ValueObjects\OperatingScheduleId;
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
