<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Exceptions;

use DomainException;

final class InvalidStatusTransitionException extends DomainException
{
    public readonly string $from;

    public readonly string $to;

    private function __construct(string $message, string $from, string $to)
    {
        parent::__construct($message);
        $this->from = $from;
        $this->to = $to;
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
