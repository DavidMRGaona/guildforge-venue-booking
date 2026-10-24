<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Exceptions;

use DomainException;

/**
 * Another booking of the same user, or of the same resource, is being saved and did not finish
 * in time.
 */
final class BookingBusyException extends DomainException
{
    public static function create(): self
    {
        return new self('Another booking of the same user or of the same resource is being saved.');
    }
}
