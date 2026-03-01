<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Application\DTOs\Response;

use Modules\VenueBookings\Domain\Entities\Booking;
use Modules\VenueBookings\Domain\Enums\BookingStatus;
use Modules\VenueBookings\Domain\ValueObjects\BookingFieldValue;

final readonly class BookingListDTO
{
    public function __construct(
        public string $id,
        public string $resourceId,
        public string $userId,
        public string $date,
        public string $startTime,
        public string $endTime,
        public BookingStatus $status,
        public string $resourceName = '',
        public string $userName = '',
        /** @var array<array{field_key: string, field_label: string, value: mixed, visibility: string}> */
        public array $fieldValues = [],
        public ?string $cancellationReason = null,
    ) {
    }

    public static function fromEntity(
        Booking $booking,
        string $resourceName = '',
        string $userName = '',
    ): self {
        return new self(
            id: $booking->id->value,
            resourceId: $booking->resourceId->value,
            userId: $booking->userId,
            date: $booking->date,
            startTime: $booking->timeRange->startTime,
            endTime: $booking->timeRange->endTime,
            status: $booking->status,
            resourceName: $resourceName,
            userName: $userName,
            fieldValues: array_map(
                fn (BookingFieldValue $fv): array => $fv->toArray(),
                $booking->fieldValues,
            ),
            cancellationReason: $booking->cancellationReason,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'resource_id' => $this->resourceId,
            'resource_name' => $this->resourceName,
            'user_id' => $this->userId,
            'user_name' => $this->userName,
            'date' => $this->date,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'is_cancellable' => $this->status->isActive(),
            'field_values' => $this->fieldValues,
            'cancellation_reason' => $this->cancellationReason,
        ];
    }
}
