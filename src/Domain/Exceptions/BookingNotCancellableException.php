<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Exceptions;

use DomainException;

final class BookingNotCancellableException extends DomainException
{
    public static function withId(string $bookingId): self
    {
        return new self("Booking '{$bookingId}' cannot be cancelled in its current status.");
    }
}
