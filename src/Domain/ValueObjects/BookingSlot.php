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
        // Listed with the day the venue opens but past its midnight, so it takes place the next day
        public bool $startsNextDay = false,
    ) {
    }

    /**
     * @return array{start_time: string, end_time: string, is_available: bool, label: ?string, starts_next_day: bool}
     */
    public function toArray(): array
    {
        return [
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'is_available' => $this->isAvailable,
            'label' => $this->label,
            'starts_next_day' => $this->startsNextDay,
        ];
    }
}
