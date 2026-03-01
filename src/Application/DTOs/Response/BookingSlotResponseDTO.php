<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Application\DTOs\Response;

use Modules\VenueBookings\Domain\ValueObjects\BookingSlot;

final readonly class BookingSlotResponseDTO
{
    public function __construct(
        public string $startTime,
        public string $endTime,
        public bool $isAvailable,
        public ?string $label = null,
    ) {
    }

    public static function fromVO(BookingSlot $slot): self
    {
        return new self(
            startTime: $slot->startTime,
            endTime: $slot->endTime,
            isAvailable: $slot->isAvailable,
            label: $slot->label,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'is_available' => $this->isAvailable,
            'label' => $this->label,
        ];
    }
}
