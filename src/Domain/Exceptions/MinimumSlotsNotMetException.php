<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Exceptions;

use DomainException;

final class MinimumSlotsNotMetException extends DomainException
{
    public static function forResource(int $required, int $given): self
    {
        return new self("A minimum of {$required} consecutive slots is required, but only {$given} were selected.");
    }
}
