<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Exceptions;

use DomainException;

final class BookingCancellationNotAllowedException extends DomainException
{
    public static function forUser(string $bookingId, string $userId): self
    {
        return new self("User '{$userId}' may not cancel booking '{$bookingId}'.");
    }
}
