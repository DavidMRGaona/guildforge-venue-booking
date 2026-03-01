<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Exceptions;

use DomainException;

final class SlotUnavailableException extends DomainException
{
    public static function forTimeRange(string $date, string $startTime, string $endTime): self
    {
        return new self("The time slot {$startTime}-{$endTime} on {$date} is not available.");
    }
}
