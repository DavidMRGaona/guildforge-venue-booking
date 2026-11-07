<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Exceptions;

use DomainException;

final class BookingNotStartedException extends DomainException
{
    public static function forSlot(string $date, string $startTime): self
    {
        return new self("The booking starting at {$startTime} on {$date} has not started yet.");
    }
}
