<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Entities;

use Modules\VenueBookings\Domain\Enums\SchedulingMode;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Modules\VenueBookings\Domain\ValueObjects\BookingSlot;
use Modules\VenueBookings\Domain\ValueObjects\DaySchedule;
use Modules\VenueBookings\Domain\ValueObjects\OperatingScheduleId;

final class OperatingSchedule
{
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
     * @return array<BookingSlot>
     */
    public function generateSlotsForDate(string $date): array
    {
        if ($this->schedulingMode === SchedulingMode::TimeBlocks) {
            return $this->generateBlocksForDate($date);
        }

        $dayOfWeek = (int) date('w', strtotime($date));
        $daySchedule = $this->getDaySchedule($dayOfWeek);

        if ($daySchedule === null || ! $daySchedule->isEnabled) {
            return [];
        }

        $slots = [];
        $currentTime = strtotime($daySchedule->openTime);
        $closeTime = strtotime($daySchedule->closeTime);
        $durationSeconds = $this->slotDurationMinutes * 60;

        if ($daySchedule->crossesMidnight()) {
            $closeTime += 86400;
        }

        while ($currentTime < $closeTime) {
            $startTime = date('H:i', $currentTime);
            $endTime = date('H:i', $currentTime + $durationSeconds);

            $slots[] = new BookingSlot(
                startTime: $startTime,
                endTime: $endTime,
                isAvailable: true,
            );

            $currentTime += $durationSeconds;
        }

        return $slots;
    }

    /**
     * @return array<BookingSlot>
     */
    private function generateBlocksForDate(string $date): array
    {
        $dayOfWeek = (int) date('w', strtotime($date));
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
}
