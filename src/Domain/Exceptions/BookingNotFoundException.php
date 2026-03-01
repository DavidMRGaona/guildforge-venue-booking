<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Exceptions;

use DomainException;

final class BookingNotFoundException extends DomainException
{
    public static function withId(string $id): self
    {
        return new self("Booking with ID '{$id}' not found.");
    }
}
