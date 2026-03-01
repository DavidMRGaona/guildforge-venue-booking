<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Services;

use Carbon\CarbonImmutable;
use Modules\VenueBookings\Application\Services\SlotAvailabilityServiceInterface;
use Modules\VenueBookings\Domain\Repositories\BookingRepositoryInterface;
use Modules\VenueBookings\Domain\Repositories\OperatingScheduleRepositoryInterface;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Modules\VenueBookings\Domain\ValueObjects\BookingSlot;
use Modules\VenueBookings\Domain\ValueObjects\TimeRange;

final readonly class SlotAvailabilityService implements SlotAvailabilityServiceInterface
{
    public function __construct(
        private OperatingScheduleRepositoryInterface $scheduleRepository,
        private BookingRepositoryInterface $bookingRepository,
        private BookingSettingsReader $settingsReader,
    ) {}

    /**
     * @return array<BookingSlot>
     */
    public function getAvailableSlotsForDate(string $resourceId, string $date): array
    {
        $resourceIdVO = BookableResourceId::fromString($resourceId);
        $schedule = $this->scheduleRepository->findByResource($resourceIdVO);

        if ($schedule === null) {
            return [];
        }

        $slots = $schedule->generateSlotsForDate($date);
        $bookings = $this->bookingRepository->getByResourceAndDate($resourceIdVO, $date);

        $tz = $this->settingsReader->getTimezone();
        $now = CarbonImmutable::now($tz);
        $isToday = $now->toDateString() === $date;
        $cutoffTime = $isToday
            ? $now->addMinutes($this->settingsReader->getMinAdvanceMinutes())->format('H:i')
            : null;

        return array_map(function (BookingSlot $slot) use ($bookings, $cutoffTime): BookingSlot {
            if ($cutoffTime !== null && $slot->startTime < $cutoffTime) {
                return new BookingSlot(
                    startTime: $slot->startTime,
                    endTime: $slot->endTime,
                    isAvailable: false,
                    label: $slot->label,
                );
            }

            foreach ($bookings as $booking) {
                if (! $booking->status->isFinal()) {
                    $slotRange = new TimeRange($slot->startTime, $slot->endTime);
                    if ($booking->timeRange->overlaps($slotRange)) {
                        return new BookingSlot(
                            startTime: $slot->startTime,
                            endTime: $slot->endTime,
                            isAvailable: false,
                            label: $slot->label,
                        );
                    }
                }
            }

            return $slot;
        }, $slots);
    }

    public function isSlotAvailable(string $resourceId, string $date, TimeRange $timeRange): bool
    {
        $resourceIdVO = BookableResourceId::fromString($resourceId);

        return ! $this->bookingRepository->hasOverlappingBooking($resourceIdVO, $date, $timeRange);
    }
}
