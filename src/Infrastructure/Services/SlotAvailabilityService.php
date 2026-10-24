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
        private BookingRules $rules,
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

        // Closed, or a date that cannot be read
        if ($slots === []) {
            return [];
        }

        // With the days around it: a booking or slot that crosses midnight meets the next day
        $bookings = $this->bookingRepository->getByResourceInDateRange(
            $resourceIdVO,
            CarbonImmutable::parse($date)->subDay()->toDateString(),
            CarbonImmutable::parse($date)->addDay()->toDateString(),
        );
        // Same rules as createBooking(): nothing beyond the days ahead, nothing before the minimum advance
        $tooFar = $this->rules->isTooFar($date);

        return array_map(function (BookingSlot $slot) use ($bookings, $date, $tooFar): BookingSlot {
            if ($tooFar || $this->rules->startsTooSoon($date, $slot->startTime)) {
                return $this->unavailable($slot);
            }

            $slotRange = new TimeRange($slot->startTime, $slot->endTime);

            foreach ($bookings as $booking) {
                if ($booking->overlaps($date, $slotRange)) {
                    return $this->unavailable($slot);
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

    private function unavailable(BookingSlot $slot): BookingSlot
    {
        return new BookingSlot(
            startTime: $slot->startTime,
            endTime: $slot->endTime,
            isAvailable: false,
            label: $slot->label,
        );
    }
}
