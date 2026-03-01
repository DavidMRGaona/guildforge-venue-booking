<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Application\DTOs\Response;

use Modules\VenueBookings\Domain\Entities\BookableResource;
use Modules\VenueBookings\Domain\Enums\BookableResourceStatus;

final readonly class BookableResourceResponseDTO
{
    /**
     * @param  array<array<string, mixed>>  $fieldDefinitions
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public ?string $description,
        public ?int $capacity,
        public BookableResourceStatus $status,
        public int $sortOrder,
        public string $schedulingMode = 'time_slots',
        public array $fieldDefinitions = [],
        public int $minConsecutiveSlots = 1,
        public int $maxConsecutiveSlots = 4,
    ) {}

    /**
     * @param  array<array<string, mixed>>  $fieldDefinitions
     */
    public static function fromEntity(
        BookableResource $resource,
        string $schedulingMode = 'time_slots',
        array $fieldDefinitions = [],
        int $minConsecutiveSlots = 1,
        int $maxConsecutiveSlots = 4,
    ): self {
        return new self(
            id: $resource->id->value,
            name: $resource->name,
            slug: $resource->slug,
            description: $resource->description,
            capacity: $resource->capacity,
            status: $resource->status,
            sortOrder: $resource->sortOrder,
            schedulingMode: $schedulingMode,
            fieldDefinitions: $fieldDefinitions,
            minConsecutiveSlots: $minConsecutiveSlots,
            maxConsecutiveSlots: $maxConsecutiveSlots,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'capacity' => $this->capacity,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'is_active' => $this->status->isBookable(),
            'sort_order' => $this->sortOrder,
            'scheduling_mode' => $this->schedulingMode,
            'field_definitions' => $this->fieldDefinitions,
            'min_consecutive_slots' => $this->minConsecutiveSlots,
            'max_consecutive_slots' => $this->maxConsecutiveSlots,
        ];
    }
}
