<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Application\DTOs;

use Modules\VenueBookings\Domain\Enums\BookableResourceStatus;

final readonly class UpdateBookableResourceDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description = null,
        public ?int $capacity = null,
        public BookableResourceStatus $status = BookableResourceStatus::Active,
        public int $sortOrder = 0,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            description: $data['description'] ?? null,
            capacity: isset($data['capacity']) ? (int) $data['capacity'] : null,
            status: $data['status'] instanceof BookableResourceStatus
                ? $data['status']
                : BookableResourceStatus::from($data['status'] ?? 'active'),
            sortOrder: (int) ($data['sort_order'] ?? 0),
        );
    }
}
