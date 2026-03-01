<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Application\DTOs;

final readonly class CreateBookingDTO
{
    /**
     * @param  array<array{field_key: string, field_label: string, value: mixed, visibility: string}>  $fieldValues
     */
    public function __construct(
        public string $resourceId,
        public string $userId,
        public string $date,
        public string $startTime,
        public string $endTime,
        public array $fieldValues = [],
        public ?string $eventId = null,
        public ?string $gameTableId = null,
        public ?string $tournamentId = null,
        public ?string $campaignId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            resourceId: $data['resource_id'],
            userId: $data['user_id'],
            date: $data['date'],
            startTime: $data['start_time'],
            endTime: $data['end_time'],
            fieldValues: $data['field_values'] ?? [],
            eventId: $data['event_id'] ?? null,
            gameTableId: $data['game_table_id'] ?? null,
            tournamentId: $data['tournament_id'] ?? null,
            campaignId: $data['campaign_id'] ?? null,
        );
    }
}
