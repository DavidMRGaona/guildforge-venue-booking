<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Application\Services;

use Modules\VenueBookings\Application\DTOs\CancelBookingDTO;
use Modules\VenueBookings\Application\DTOs\CreateBookingDTO;
use Modules\VenueBookings\Application\DTOs\Response\BookingResponseDTO;

interface BookingServiceInterface
{
    /**
     * Create a booking. Refused for any caller once the slot has started in the venue timezone.
     *
     * @throws \Modules\VenueBookings\Domain\Exceptions\BookingAlreadyStartedException
     * @throws \Modules\VenueBookings\Domain\Exceptions\MinimumSlotsNotMetException
     * @throws \Modules\VenueBookings\Domain\Exceptions\SlotUnavailableException
     */
    public function createBooking(CreateBookingDTO $dto): BookingResponseDTO;

    public function confirmBooking(string $bookingId): BookingResponseDTO;

    /**
     * Cancel a booking on behalf of the user in the DTO, when canCancel() allows it.
     *
     * @throws \Modules\VenueBookings\Domain\Exceptions\BookingCancellationNotAllowedException
     */
    public function cancelBooking(CancelBookingDTO $dto): BookingResponseDTO;

    /**
     * Whether the user may cancel a booking owned by $bookingOwnerId on the website:
     * their own with the cancel_own permission, anyone's with cancel_any.
     */
    public function canCancel(string $userId, string $bookingOwnerId): bool;

    /**
     * Cancel any booking from the admin panel. The panel authorizes the caller.
     */
    public function cancelBookingAsStaff(CancelBookingDTO $dto): BookingResponseDTO;

    public function rejectBooking(string $bookingId, ?string $reason = null): BookingResponseDTO;

    public function markCompleted(string $bookingId): BookingResponseDTO;

    public function markNoShow(string $bookingId): BookingResponseDTO;
}
