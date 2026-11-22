<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Application\Services;

use Modules\VenueBookings\Application\DTOs\Response\BookingCalendarEventDTO;
use Modules\VenueBookings\Application\DTOs\Response\BookingListDTO;
use Modules\VenueBookings\Application\DTOs\Response\BookingResponseDTO;
use Modules\VenueBookings\Application\DTOs\Response\BookingSlotResponseDTO;

interface BookingQueryServiceInterface
{
    public function getBookingById(string $bookingId): ?BookingResponseDTO;

    /**
     * @return array<BookingListDTO>
     */
    public function getBookingsForDate(string $resourceId, string $date): array;

    /**
     * The events never say who booked: with a viewer, each one only says whether it is theirs.
     *
     * @return array<BookingCalendarEventDTO>
     */
    public function getCalendarEvents(string $resourceId, string $fromDate, string $toDate, ?string $viewerId = null): array;

    /**
     * @return array<BookingListDTO>
     */
    public function getUserBookings(string $userId): array;

    /**
     * @return array<BookingSlotResponseDTO>
     */
    public function getAvailableSlots(string $resourceId, string $date): array;
}
