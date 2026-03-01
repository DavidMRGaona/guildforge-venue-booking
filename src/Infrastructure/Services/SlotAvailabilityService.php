<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Services;

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
    ) {
    }

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

        return array_map(function (BookingSlot $slot) use ($bookings): BookingSlot {
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
