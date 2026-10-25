<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Services;

use Carbon\CarbonImmutable;
use Modules\VenueBookings\Application\DTOs\Response\BookingCalendarEventDTO;
use Modules\VenueBookings\Application\DTOs\Response\BookingListDTO;
use Modules\VenueBookings\Application\DTOs\Response\BookingResponseDTO;
use Modules\VenueBookings\Application\DTOs\Response\BookingSlotResponseDTO;
use Modules\VenueBookings\Application\Services\BookingQueryServiceInterface;
use Modules\VenueBookings\Application\Services\SlotAvailabilityServiceInterface;
use Modules\VenueBookings\Domain\Entities\Booking;
use Modules\VenueBookings\Domain\Repositories\BookingRepositoryInterface;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Modules\VenueBookings\Domain\ValueObjects\BookingId;

final readonly class BookingQueryService implements BookingQueryServiceInterface
{
    public function __construct(
        private BookingRepositoryInterface $bookingRepository,
        private SlotAvailabilityServiceInterface $slotAvailabilityService,
        private BookingSettingsReader $settingsReader,
    ) {}

    public function getBookingById(string $bookingId): ?BookingResponseDTO
    {
        $booking = $this->bookingRepository->find(BookingId::fromString($bookingId));

        return $booking instanceof Booking ? BookingResponseDTO::fromEntity($booking) : null;
    }

    /**
     * @return array<BookingListDTO>
     */
    public function getBookingsForDate(string $resourceId, string $date): array
    {
        $bookings = $this->bookingRepository->getByResourceAndDate(
            BookableResourceId::fromString($resourceId),
            $date,
        );

        $timezone = $this->settingsReader->getDateTimeZone();
        $now = CarbonImmutable::now($timezone);

        return array_map(
            fn (Booking $booking): BookingListDTO => BookingListDTO::fromEntity(
                $booking,
                hasStarted: $booking->hasStarted($now, $timezone),
            ),
            $bookings,
        );
    }

    /**
     * @return array<BookingCalendarEventDTO>
     */
    public function getCalendarEvents(string $resourceId, string $fromDate, string $toDate): array
    {
        $bookings = $this->bookingRepository->getByResourceInDateRange(
            BookableResourceId::fromString($resourceId),
            $fromDate,
            $toDate,
        );

        return array_map(
            fn (Booking $booking): BookingCalendarEventDTO => BookingCalendarEventDTO::fromEntity($booking),
            $bookings,
        );
    }

    /**
     * @return array<BookingListDTO>
     */
    public function getUserBookings(string $userId): array
    {
        $bookings = $this->bookingRepository->getByUser($userId);
        $timezone = $this->settingsReader->getDateTimeZone();
        $now = CarbonImmutable::now($timezone);

        return array_map(
            fn (Booking $booking): BookingListDTO => BookingListDTO::fromEntity(
                $booking,
                hasStarted: $booking->hasStarted($now, $timezone),
            ),
            $bookings,
        );
    }

    /**
     * @return array<BookingSlotResponseDTO>
     */
    public function getAvailableSlots(string $resourceId, string $date): array
    {
        $slots = $this->slotAvailabilityService->getAvailableSlotsForDate($resourceId, $date);

        return array_map(
            BookingSlotResponseDTO::fromVO(...),
            $slots,
        );
    }
}
