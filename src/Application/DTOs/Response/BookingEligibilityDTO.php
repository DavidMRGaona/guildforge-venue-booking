<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Application\DTOs\Response;

final readonly class BookingEligibilityDTO
{
    /**
     * @param  array<string>  $reasons
     */
    public function __construct(
        public bool $canBook,
        public array $reasons = [],
    ) {
    }

    public static function eligible(): self
    {
        return new self(canBook: true);
    }

    /**
     * @param  array<string>  $reasons
     */
    public static function ineligible(array $reasons): self
    {
        return new self(canBook: false, reasons: $reasons);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'can_book' => $this->canBook,
            'reasons' => $this->reasons,
        ];
    }
}
