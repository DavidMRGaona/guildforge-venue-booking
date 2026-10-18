<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Entities;

use DateTimeImmutable;
use DateTimeZone;
use Modules\VenueBookings\Domain\Enums\SchedulingMode;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Modules\VenueBookings\Domain\ValueObjects\BookingSlot;
use Modules\VenueBookings\Domain\ValueObjects\DaySchedule;
use Modules\VenueBookings\Domain\ValueObjects\OperatingScheduleId;
use Modules\VenueBookings\Domain\ValueObjects\TimeRange;

final class OperatingSchedule
{
    private const int MINUTES_PER_DAY = 1440;

    /**
     * @param  array<DaySchedule>  $daySchedules
     */
    public function __construct(
        public readonly OperatingScheduleId $id,
        public readonly BookableResourceId $resourceId,
        public int $slotDurationMinutes = 60,
        public int $minConsecutiveSlots = 1,
        public int $maxConsecutiveSlots = 4,
        public array $daySchedules = [],
        public SchedulingMode $schedulingMode = SchedulingMode::TimeSlots,
    ) {}

    public function isOperatingOn(int $dayOfWeek): bool
    {
        $daySchedule = $this->getDaySchedule($dayOfWeek);

        return $daySchedule !== null && $daySchedule->isEnabled;
    }

    public function getDaySchedule(int $dayOfWeek): ?DaySchedule
    {
        foreach ($this->daySchedules as $schedule) {
            if ($schedule->dayOfWeek === $dayOfWeek) {
                return $schedule;
            }
        }

        return null;
    }

    /**
     * Slots of $date (Y-m-d). Times are wall-clock values counted in minutes from
     * midnight, so they depend neither on PHP's default timezone nor on today's date.
     * As before, the last slot may run past the closing time when the slot duration
     * does not divide the opening hours.
     *
     * @return array<BookingSlot>
     */
    public function generateSlotsForDate(string $date): array
    {
        $dayOfWeek = self::dayOfWeek($date);

        if ($dayOfWeek === null) {
            return [];
        }

        if ($this->schedulingMode === SchedulingMode::TimeBlocks) {
            return $this->generateBlocksFor($dayOfWeek);
        }

        $daySchedule = $this->getDaySchedule($dayOfWeek);

        if ($daySchedule === null || ! $daySchedule->isEnabled || $this->slotDurationMinutes < 1) {
            return [];
        }

        $open = self::toMinutes($daySchedule->openTime);
        $close = self::toMinutes($daySchedule->closeTime);

        // Open until the next day (e.g. 22:00-02:00)
        if ($close < $open) {
            $close += self::MINUTES_PER_DAY;
        }

        $slots = [];

        for ($start = $open; $start < $close; $start += $this->slotDurationMinutes) {
            $slots[] = new BookingSlot(
                startTime: self::formatMinutes($start),
                endTime: self::formatMinutes($start + $this->slotDurationMinutes),
                isAvailable: true,
            );
        }

        return $slots;
    }

    /**
     * How many slots of $date the range covers exactly, or null when it is not made of
     * them: the day is closed, or the range does not start and end on slot boundaries.
     * In block mode the range must be one whole block, which counts as one slot.
     */
    public function slotsCoveredBy(string $date, TimeRange $range): ?int
    {
        $slots = $this->generateSlotsForDate($date);
        $start = self::toMinutes($range->startTime);
        $end = self::toMinutes($range->endTime);

        if ($this->schedulingMode === SchedulingMode::TimeBlocks) {
            foreach ($slots as $block) {
                if (self::toMinutes($block->startTime) === $start && self::toMinutes($block->endTime) === $end) {
                    return 1;
                }
            }

            return null;
        }

        // Time slots are contiguous: walk from the slot the range starts on to the one it ends on
        $count = null;

        foreach ($slots as $slot) {
            if ($count === null) {
                if (self::toMinutes($slot->startTime) !== $start) {
                    continue;
                }

                $count = 0;
            }

            $count++;

            if (self::toMinutes($slot->endTime) === $end) {
                return $count;
            }
        }

        return null;
    }

    /**
     * @return array<BookingSlot>
     */
    private function generateBlocksFor(int $dayOfWeek): array
    {
        $blocks = [];

        foreach ($this->daySchedules as $schedule) {
            if ($schedule->dayOfWeek === $dayOfWeek && $schedule->isEnabled) {
                $blocks[] = new BookingSlot(
                    startTime: $schedule->openTime,
                    endTime: $schedule->closeTime,
                    isAvailable: true,
                    label: $schedule->label,
                );
            }
        }

        return $blocks;
    }

    /**
     * Day of week (0 = Sunday) of a Y-m-d date, or null when it cannot be read.
     */
    private static function dayOfWeek(string $date): ?int
    {
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));

        return $day === false ? null : (int) $day->format('w');
    }

    /**
     * Minutes from midnight of "H:MM", "HH:MM" or "HH:MM:SS".
     */
    private static function toMinutes(string $time): int
    {
        $parts = explode(':', $time);

        return (int) $parts[0] * 60 + (int) ($parts[1] ?? 0);
    }

    /**
     * "HH:MM" of a minute count, wrapping past midnight.
     */
    private static function formatMinutes(int $minutes): string
    {
        $minutes %= self::MINUTES_PER_DAY;

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
