<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Exceptions;

use DomainException;

final class TooSoonToBookException extends DomainException
{
    public static function withMinAdvance(int $minAdvanceMinutes): self
    {
        return new self("Bookings must be made at least {$minAdvanceMinutes} minutes in advance.");
    }
}
