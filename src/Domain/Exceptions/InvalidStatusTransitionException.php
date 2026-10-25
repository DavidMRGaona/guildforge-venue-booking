<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Exceptions;

use DomainException;

final class InvalidStatusTransitionException extends DomainException
{
    private function __construct(string $message, public readonly string $from, public readonly string $to)
    {
        parent::__construct($message);
    }

    public static function fromTo(string $from, string $to): self
    {
        return new self(
            "Invalid status transition from '{$from}' to '{$to}'.",
            $from,
            $to
        );
    }
}
