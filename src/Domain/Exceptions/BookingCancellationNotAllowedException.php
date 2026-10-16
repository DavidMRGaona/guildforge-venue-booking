<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Exceptions;

use DomainException;

final class BookingCancellationNotAllowedException extends DomainException
{
    private bool $bookingStarted = false;

    public static function forUser(string $bookingId, string $userId): self
    {
        return new self("User '{$userId}' may not cancel booking '{$bookingId}'.");
    }

    public static function alreadyStarted(string $bookingId): self
    {
        $exception = new self("Booking '{$bookingId}' has already started and can no longer be cancelled from the website.");
        $exception->bookingStarted = true;

        return $exception;
    }

    /**
     * True when the user may cancel the booking but it has already started;
     * false when the user may not cancel it at all.
     */
    public function bookingHasStarted(): bool
    {
        return $this->bookingStarted;
    }
}
