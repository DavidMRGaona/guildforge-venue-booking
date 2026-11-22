<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Application\DTOs\Response;

use DateTimeZone;
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
        // Whether the person looking booked it; the calendar never says who did
        public bool $isMine = false,
    ) {}

    public static function fromEntity(Booking $booking, ?string $viewerId = null): self
    {
        return new self(
            id: $booking->id->value,
            title: __('venue-bookings::messages.calendar.reserved'),
            start: $booking->date.'T'.$booking->timeRange->startTime,
            // A range that crosses midnight ends on the next day; times are wall clock, so UTC only picks the date
            end: $booking->endsAt(new DateTimeZone('UTC'))->format('Y-m-d').'T'.$booking->timeRange->endTime,
            color: $booking->status->calendarCssColor(),
            status: $booking->status->value,
            statusLabel: $booking->status->label(),
            isMine: $viewerId !== null && strtolower($viewerId) === strtolower($booking->userId),
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
            'is_mine' => $this->isMine,
        ];
    }
}
