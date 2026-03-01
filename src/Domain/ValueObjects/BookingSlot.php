<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\ValueObjects;

final readonly class BookingSlot
{
    public function __construct(
        public string $startTime,
        public string $endTime,
        public bool $isAvailable,
        public ?string $label = null,
    ) {
    }

    /**
     * @return array{start_time: string, end_time: string, is_available: bool, label: ?string}
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
