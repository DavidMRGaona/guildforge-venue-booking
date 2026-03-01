<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Application\DTOs\Response;

use DateTimeInterface;
use Modules\VenueBookings\Domain\Entities\Booking;
use Modules\VenueBookings\Domain\Enums\BookingStatus;

final readonly class BookingResponseDTO
{
    /**
     * @param  array<array{field_key: string, field_label: string, value: mixed, visibility: string}>  $fieldValues
     */
    public function __construct(
        public string $id,
        public string $resourceId,
        public string $userId,
        public string $date,
        public string $startTime,
        public string $endTime,
        public BookingStatus $status,
        public array $fieldValues,
        public ?string $eventId,
        public ?string $gameTableId,
        public ?string $tournamentId,
        public ?string $campaignId,
        public ?string $cancellationReason,
        public ?string $adminNotes,
        public ?DateTimeInterface $confirmedAt,
        public ?DateTimeInterface $cancelledAt,
        public string $resourceName = '',
        public string $userName = '',
    ) {}

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
            fieldValues: array_map(
                fn ($fv): array => $fv->toArray(),
                $booking->fieldValues,
            ),
            eventId: $booking->eventId,
            gameTableId: $booking->gameTableId,
            tournamentId: $booking->tournamentId,
            campaignId: $booking->campaignId,
            cancellationReason: $booking->cancellationReason,
            adminNotes: $booking->adminNotes,
            confirmedAt: $booking->confirmedAt,
            cancelledAt: $booking->cancelledAt,
            resourceName: $resourceName,
            userName: $userName,
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
            'event_id' => $this->eventId,
            'game_table_id' => $this->gameTableId,
            'tournament_id' => $this->tournamentId,
            'campaign_id' => $this->campaignId,
            'cancellation_reason' => $this->cancellationReason,
            'admin_notes' => $this->adminNotes,
            'confirmed_at' => $this->confirmedAt?->format('c'),
            'cancelled_at' => $this->cancelledAt?->format('c'),
        ];
    }
}
