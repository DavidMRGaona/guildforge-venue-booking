<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Exceptions;

use DomainException;

final class SlotOutsideScheduleException extends DomainException
{
    public static function forTimeRange(string $date, string $startTime, string $endTime): self
    {
        return new self("The time range {$startTime}-{$endTime} on {$date} is not made of the slots of the resource's schedule.");
    }
}
