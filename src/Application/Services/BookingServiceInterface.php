<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Application\Services;

use Modules\VenueBookings\Application\DTOs\CancelBookingDTO;
use Modules\VenueBookings\Application\DTOs\CreateBookingDTO;
use Modules\VenueBookings\Application\DTOs\Response\BookingResponseDTO;

interface BookingServiceInterface
{
    /**
     * Create a booking. Refused for any caller, administrators included, once the slot has
     * started (venue timezone), when a settings rule forbids it (active bookings per user,
     * minimum advance, days ahead), when the range is not made of the resource's slots for
     * that day or spans fewer or more consecutive slots than allowed, or when it overlaps
     * an active booking.
     *
     * @throws \Modules\VenueBookings\Domain\Exceptions\BookingAlreadyStartedException
     * @throws \Modules\VenueBookings\Domain\Exceptions\BookingLimitExceededException
     * @throws \Modules\VenueBookings\Domain\Exceptions\TooSoonToBookException
     * @throws \Modules\VenueBookings\Domain\Exceptions\TooFarToBookException
     * @throws \Modules\VenueBookings\Domain\Exceptions\SlotOutsideScheduleException
     * @throws \Modules\VenueBookings\Domain\Exceptions\MinimumSlotsNotMetException
     * @throws \Modules\VenueBookings\Domain\Exceptions\MaximumSlotsExceededException
     * @throws \Modules\VenueBookings\Domain\Exceptions\SlotUnavailableException
     */
    public function createBooking(CreateBookingDTO $dto): BookingResponseDTO;

    public function confirmBooking(string $bookingId): BookingResponseDTO;

    /**
     * Cancel a booking on behalf of the user in the DTO, when canCancel() allows it and the
     * booking has not started yet (venue timezone). Staff use cancelBookingAsStaff(), which has no cutoff.
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
