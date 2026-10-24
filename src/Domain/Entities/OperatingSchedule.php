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
     * Slots of $date (Y-m-d), the day the venue opens. Times are wall-clock values counted in
     * minutes from midnight, so they depend neither on PHP's default timezone nor on today's
     * date. On a day open past midnight (e.g. 22:00-02:00), the slots after midnight are listed
     * here too and marked as starting the next day. As before, the last slot may run past the
     * closing time when the slot duration does not divide the opening hours.
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
                startsNextDay: $start >= self::MINUTES_PER_DAY,
            );
        }

        return $slots;
    }

    /**
     * How many slots listed for $date (the day the venue opens) the range covers exactly, or
     * null when it is not made of them: the day is closed, or the range does not start and end
     * on slot boundaries. In block mode the range must be one whole block, which counts as one slot.
     */
    public function slotsCoveredBy(string $date, TimeRange $range): ?int
    {
        return $this->findRun($date, $range)['count'] ?? null;
    }

    /**
     * Like slotsCoveredBy(), for a range that starts on $startDate (the calendar day): made of
     * the slots of $startDate that start before its midnight, or of the slots the day before
     * lists past its midnight.
     */
    public function slotsCoveredOn(string $startDate, TimeRange $range): ?int
    {
        $own = $this->findRun($startDate, $range);

        if ($own !== null && ! $own['startsNextDay']) {
            return $own['count'];
        }

        $dayBefore = self::shiftDays($startDate, -1);
        $tail = $dayBefore === null ? null : $this->findRun($dayBefore, $range);

        return $tail !== null && $tail['startsNextDay'] ? $tail['count'] : null;
    }

    /**
     * The calendar day a range picked from the slots of $openingDate starts on: the next day
     * when its first slot is past that day's midnight, $openingDate otherwise.
     */
    public function startDateOf(string $openingDate, TimeRange $range): string
    {
        $run = $this->findRun($openingDate, $range);

        if ($run === null || ! $run['startsNextDay']) {
            return $openingDate;
        }

        return self::shiftDays($openingDate, 1) ?? $openingDate;
    }

    /**
     * The run of slots listed for $date that the range covers exactly, and whether its first
     * slot starts the next day.
     *
     * @return array{count: int, startsNextDay: bool}|null
     */
    private function findRun(string $date, TimeRange $range): ?array
    {
        $slots = $this->generateSlotsForDate($date);
        $start = self::toMinutes($range->startTime);
        $end = self::toMinutes($range->endTime);

        if ($this->schedulingMode === SchedulingMode::TimeBlocks) {
            foreach ($slots as $block) {
                if (self::toMinutes($block->startTime) === $start && self::toMinutes($block->endTime) === $end) {
                    return ['count' => 1, 'startsNextDay' => false];
                }
            }

            return null;
        }

        // Time slots are contiguous: walk from the slot the range starts on to the one it ends on
        $count = null;
        $startsNextDay = false;

        foreach ($slots as $slot) {
            if ($count === null) {
                if (self::toMinutes($slot->startTime) !== $start) {
                    continue;
                }

                $count = 0;
                $startsNextDay = $slot->startsNextDay;
            }

            $count++;

            if (self::toMinutes($slot->endTime) === $end) {
                return ['count' => $count, 'startsNextDay' => $startsNextDay];
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
     * The Y-m-d date $days away from $date, or null when $date cannot be read.
     */
    private static function shiftDays(string $date, int $days): ?string
    {
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));

        return $day === false ? null : $day->modify(sprintf('%+d day', $days))->format('Y-m-d');
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
