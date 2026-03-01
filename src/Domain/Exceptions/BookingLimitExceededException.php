<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Exceptions;

use DomainException;

final class BookingLimitExceededException extends DomainException
{
    public static function forUser(string $userId, int $maxBookings): self
    {
        return new self("User '{$userId}' has reached the maximum of {$maxBookings} active bookings.");
    }
}
