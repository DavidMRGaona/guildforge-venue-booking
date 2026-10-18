<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Exceptions;

use DomainException;

final class BookableResourceUnavailableException extends DomainException
{
    public static function withId(string $id): self
    {
        return new self("Bookable resource with ID '{$id}' is not active and cannot be booked.");
    }
}
