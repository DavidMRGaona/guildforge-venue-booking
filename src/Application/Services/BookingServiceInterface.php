<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Application\Services;

use Modules\VenueBookings\Application\DTOs\CancelBookingDTO;
use Modules\VenueBookings\Application\DTOs\CreateBookingDTO;
use Modules\VenueBookings\Application\DTOs\Response\BookingResponseDTO;

interface BookingServiceInterface
{
    public function createBooking(CreateBookingDTO $dto): BookingResponseDTO;

    public function confirmBooking(string $bookingId): BookingResponseDTO;

    /**
     * Cancel a booking on behalf of the user in the DTO: only their own bookings,
     * unless they hold the cancel_any permission.
     *
     * @throws \Modules\VenueBookings\Domain\Exceptions\BookingCancellationNotAllowedException
     */
    public function cancelBooking(CancelBookingDTO $dto): BookingResponseDTO;

    /**
     * Cancel any booking from the admin panel. The panel authorizes the caller.
     */
    public function cancelBookingAsStaff(CancelBookingDTO $dto): BookingResponseDTO;

    public function rejectBooking(string $bookingId, ?string $reason = null): BookingResponseDTO;

    public function markCompleted(string $bookingId): BookingResponseDTO;

    public function markNoShow(string $bookingId): BookingResponseDTO;
}
