<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Exceptions;

use DomainException;

final class MaximumSlotsExceededException extends DomainException
{
    public static function forResource(int $maximum, int $given): self
    {
        return new self("At most {$maximum} consecutive slots can be booked, but {$given} were selected.");
    }
}
