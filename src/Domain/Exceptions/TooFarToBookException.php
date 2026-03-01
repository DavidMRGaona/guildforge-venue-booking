<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Exceptions;

use DomainException;

final class TooFarToBookException extends DomainException
{
    public static function withMaxDays(int $maxFutureDays): self
    {
        return new self("Bookings cannot be made more than {$maxFutureDays} days in advance.");
    }
}
