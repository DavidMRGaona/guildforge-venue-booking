<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Exceptions;

use DomainException;

final class BookingNotEndedException extends DomainException
{
    public static function forSlot(string $date, string $endTime): self
    {
        return new self("The booking of {$date} ending at {$endTime} has not ended yet.");
    }
}
