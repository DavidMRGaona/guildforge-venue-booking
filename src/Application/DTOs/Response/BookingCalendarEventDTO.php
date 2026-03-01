<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Application\DTOs\Response;

use Modules\VenueBookings\Domain\Entities\Booking;

final readonly class BookingCalendarEventDTO
{
    public function __construct(
        public string $id,
        public string $title,
        public string $start,
        public string $end,
        public string $color,
        public string $status,
        public string $statusLabel,
        public ?string $userId = null,
    ) {
    }

    public static function fromEntity(Booking $booking, string $userName = ''): self
    {
        $title = $userName !== '' ? $userName : __('venue-bookings::messages.calendar.reserved');

        return new self(
            id: $booking->id->value,
            title: $title,
            start: $booking->date . 'T' . $booking->timeRange->startTime,
            end: $booking->date . 'T' . $booking->timeRange->endTime,
            color: $booking->status->calendarCssColor(),
            status: $booking->status->value,
            statusLabel: $booking->status->label(),
            userId: $booking->userId,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'start' => $this->start,
            'end' => $this->end,
            'color' => $this->color,
            'status' => $this->status,
            'status_label' => $this->statusLabel,
            'user_id' => $this->userId,
        ];
    }
}
