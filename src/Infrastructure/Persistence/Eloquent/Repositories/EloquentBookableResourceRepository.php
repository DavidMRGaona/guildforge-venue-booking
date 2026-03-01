<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Repositories;

use Modules\VenueBookings\Domain\Entities\BookableResource;
use Modules\VenueBookings\Domain\Enums\BookableResourceStatus;
use Modules\VenueBookings\Domain\Exceptions\BookableResourceNotFoundException;
use Modules\VenueBookings\Domain\Repositories\BookableResourceRepositoryInterface;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Modules\VenueBookings\Domain\ValueObjects\ResourceFieldConfig;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;

final readonly class EloquentBookableResourceRepository implements BookableResourceRepositoryInterface
{
    public function save(BookableResource $resource): void
    {
        BookableResourceModel::query()->updateOrCreate(
            ['id' => $resource->id->value],
            $this->toArray($resource),
        );
    }

    public function find(BookableResourceId $id): ?BookableResource
    {
        $model = BookableResourceModel::query()->find($id->value);

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function findOrFail(BookableResourceId $id): BookableResource
    {
        $entity = $this->find($id);

        if ($entity === null) {
            throw BookableResourceNotFoundException::withId($id->value);
        }

        return $entity;
    }

    public function delete(BookableResourceId $id): void
    {
        BookableResourceModel::query()->where('id', $id->value)->delete();
    }

    /**
     * @return array<BookableResource>
     */
    public function getByStatus(BookableResourceStatus $status): array
    {
        return BookableResourceModel::query()
            ->where('status', $status->value)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (BookableResourceModel $model): BookableResource => $this->toEntity($model))
            ->all();
    }

    /**
     * @return array<BookableResource>
     */
    public function getActive(): array
    {
        return $this->getByStatus(BookableResourceStatus::Active);
    }

    /**
     * @return array<BookableResource>
     */
    public function all(): array
    {
        return BookableResourceModel::query()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (BookableResourceModel $model): BookableResource => $this->toEntity($model))
            ->all();
    }

    public function findBySlug(string $slug): ?BookableResource
    {
        $model = BookableResourceModel::query()->where('slug', $slug)->first();

        return $model !== null ? $this->toEntity($model) : null;
    }

    private function toEntity(BookableResourceModel $model): BookableResource
    {
        return new BookableResource(
            id: BookableResourceId::fromString($model->id),
            name: $model->name,
            slug: $model->slug,
            description: $model->description,
            capacity: $model->capacity,
            status: $model->status,
            sortOrder: $model->sort_order,
            fieldConfig: is_array($model->field_config)
                ? ResourceFieldConfig::fromArray($model->field_config)
                : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(BookableResource $resource): array
    {
        return [
            'id' => $resource->id->value,
            'name' => $resource->name,
            'slug' => $resource->slug,
            'description' => $resource->description,
            'capacity' => $resource->capacity,
            'status' => $resource->status->value,
            'sort_order' => $resource->sortOrder,
            'field_config' => $resource->fieldConfig?->toArray(),
        ];
    }
}
