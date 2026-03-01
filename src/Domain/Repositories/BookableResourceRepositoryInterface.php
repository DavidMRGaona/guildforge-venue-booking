<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Repositories;

use Modules\VenueBookings\Domain\Entities\BookableResource;
use Modules\VenueBookings\Domain\Enums\BookableResourceStatus;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;

interface BookableResourceRepositoryInterface
{
    public function save(BookableResource $resource): void;

    public function find(BookableResourceId $id): ?BookableResource;

    public function findOrFail(BookableResourceId $id): BookableResource;

    public function delete(BookableResourceId $id): void;

    /**
     * @return array<BookableResource>
     */
    public function getByStatus(BookableResourceStatus $status): array;

    /**
     * @return array<BookableResource>
     */
    public function getActive(): array;

    /**
     * @return array<BookableResource>
     */
    public function all(): array;

    public function findBySlug(string $slug): ?BookableResource;
}
