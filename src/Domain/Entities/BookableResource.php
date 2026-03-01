<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Entities;

use Modules\VenueBookings\Domain\Enums\BookableResourceStatus;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Modules\VenueBookings\Domain\ValueObjects\ResourceFieldConfig;

final class BookableResource
{
    public function __construct(
        public readonly BookableResourceId $id,
        public string $name,
        public string $slug,
        public ?string $description,
        public ?int $capacity,
        public BookableResourceStatus $status,
        public int $sortOrder = 0,
        public ?ResourceFieldConfig $fieldConfig = null,
    ) {}

    public function isActive(): bool
    {
        return $this->status === BookableResourceStatus::Active;
    }

    public function activate(): void
    {
        $this->status = BookableResourceStatus::Active;
    }

    public function deactivate(): void
    {
        $this->status = BookableResourceStatus::Inactive;
    }
}
