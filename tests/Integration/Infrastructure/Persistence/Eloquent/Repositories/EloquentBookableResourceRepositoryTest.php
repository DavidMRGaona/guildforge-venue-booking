<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Integration\Infrastructure\Persistence\Eloquent\Repositories;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\VenueBookings\Domain\Entities\BookableResource;
use Modules\VenueBookings\Domain\Enums\BookableResourceStatus;
use Modules\VenueBookings\Domain\Exceptions\BookableResourceNotFoundException;
use Modules\VenueBookings\Domain\Repositories\BookableResourceRepositoryInterface;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Repositories\EloquentBookableResourceRepository;
use Tests\TestCase;

final class EloquentBookableResourceRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentBookableResourceRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentBookableResourceRepository();
    }

    public function test_it_implements_repository_interface(): void
    {
        $this->assertInstanceOf(BookableResourceRepositoryInterface::class, $this->repository);
    }

    public function test_it_saves_new_resource(): void
    {
        $id = BookableResourceId::generate();
        $resource = new BookableResource(
            id: $id,
            name: 'Main Hall',
            slug: 'main-hall',
            description: 'A large hall for events',
            capacity: 50,
            status: BookableResourceStatus::Active,
            sortOrder: 1,
        );

        $this->repository->save($resource);

        $this->assertDatabaseHas('venuebookings_resources', [
            'id' => $id->value,
            'name' => 'Main Hall',
            'slug' => 'main-hall',
            'description' => 'A large hall for events',
            'capacity' => 50,
            'status' => 'active',
            'sort_order' => 1,
        ]);
    }

    public function test_it_updates_existing_resource(): void
    {
        $id = BookableResourceId::generate();

        BookableResourceModel::create([
            'id' => $id->value,
            'name' => 'Same Name',
            'slug' => 'same-name',
            'description' => 'Old description',
            'capacity' => 10,
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $updated = new BookableResource(
            id: $id,
            name: 'Same Name',
            slug: 'same-name',
            description: 'New description',
            capacity: 25,
            status: BookableResourceStatus::Maintenance,
            sortOrder: 5,
        );

        $this->repository->save($updated);

        $this->assertDatabaseHas('venuebookings_resources', [
            'id' => $id->value,
            'name' => 'Same Name',
            'slug' => 'same-name',
            'description' => 'New description',
            'capacity' => 25,
            'status' => 'maintenance',
            'sort_order' => 5,
        ]);

        $this->assertDatabaseCount('venuebookings_resources', 1);
    }

    public function test_it_finds_resource_by_id(): void
    {
        $id = BookableResourceId::generate();

        BookableResourceModel::create([
            'id' => $id->value,
            'name' => 'Board Game Room',
            'slug' => 'board-game-room',
            'description' => 'A cozy room',
            'capacity' => 12,
            'status' => 'active',
            'sort_order' => 2,
        ]);

        $resource = $this->repository->find($id);

        $this->assertNotNull($resource);
        $this->assertTrue($id->equals($resource->id));
        $this->assertEquals('Board Game Room', $resource->name);
        $this->assertEquals('board-game-room', $resource->slug);
        $this->assertEquals('A cozy room', $resource->description);
        $this->assertEquals(12, $resource->capacity);
        $this->assertEquals(BookableResourceStatus::Active, $resource->status);
        $this->assertEquals(2, $resource->sortOrder);
    }

    public function test_it_returns_null_when_resource_not_found(): void
    {
        $nonExistentId = BookableResourceId::generate();

        $resource = $this->repository->find($nonExistentId);

        $this->assertNull($resource);
    }

    public function test_find_or_fail_throws_when_not_found(): void
    {
        $nonExistentId = BookableResourceId::generate();

        $this->expectException(BookableResourceNotFoundException::class);

        $this->repository->findOrFail($nonExistentId);
    }

    public function test_find_or_fail_returns_entity_when_found(): void
    {
        $id = BookableResourceId::generate();

        BookableResourceModel::create([
            'id' => $id->value,
            'name' => 'Found Resource',
            'slug' => 'found-resource',
            'description' => null,
            'capacity' => null,
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $resource = $this->repository->findOrFail($id);

        $this->assertTrue($id->equals($resource->id));
        $this->assertEquals('Found Resource', $resource->name);
    }

    public function test_it_gets_resources_by_status(): void
    {
        BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Active Room',
            'slug' => 'active-room',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Inactive Room',
            'slug' => 'inactive-room',
            'status' => 'inactive',
            'sort_order' => 0,
        ]);

        BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Maintenance Room',
            'slug' => 'maintenance-room',
            'status' => 'maintenance',
            'sort_order' => 0,
        ]);

        $activeResources = $this->repository->getByStatus(BookableResourceStatus::Active);

        $this->assertCount(1, $activeResources);
        $this->assertEquals('Active Room', $activeResources[0]->name);

        $inactiveResources = $this->repository->getByStatus(BookableResourceStatus::Inactive);

        $this->assertCount(1, $inactiveResources);
        $this->assertEquals('Inactive Room', $inactiveResources[0]->name);
    }

    public function test_it_gets_active_resources(): void
    {
        BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Active One',
            'slug' => 'active-one',
            'status' => 'active',
            'sort_order' => 1,
        ]);

        BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Inactive One',
            'slug' => 'inactive-one',
            'status' => 'inactive',
            'sort_order' => 0,
        ]);

        BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Active Two',
            'slug' => 'active-two',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $activeResources = $this->repository->getActive();

        $this->assertCount(2, $activeResources);
    }

    public function test_it_returns_all_resources_ordered_by_sort_order(): void
    {
        BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Third',
            'slug' => 'third',
            'status' => 'active',
            'sort_order' => 3,
        ]);

        BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'First',
            'slug' => 'first',
            'status' => 'inactive',
            'sort_order' => 1,
        ]);

        BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Second',
            'slug' => 'second',
            'status' => 'maintenance',
            'sort_order' => 2,
        ]);

        $allResources = $this->repository->all();

        $this->assertCount(3, $allResources);
        $this->assertEquals('First', $allResources[0]->name);
        $this->assertEquals('Second', $allResources[1]->name);
        $this->assertEquals('Third', $allResources[2]->name);
    }

    public function test_it_gets_by_status_ordered_by_sort_order(): void
    {
        BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Beta Room',
            'slug' => 'beta-room',
            'status' => 'active',
            'sort_order' => 2,
        ]);

        BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Alpha Room',
            'slug' => 'alpha-room',
            'status' => 'active',
            'sort_order' => 1,
        ]);

        $resources = $this->repository->getByStatus(BookableResourceStatus::Active);

        $this->assertCount(2, $resources);
        $this->assertEquals('Alpha Room', $resources[0]->name);
        $this->assertEquals('Beta Room', $resources[1]->name);
    }

    public function test_it_finds_resource_by_slug(): void
    {
        $id = BookableResourceId::generate();

        BookableResourceModel::create([
            'id' => $id->value,
            'name' => 'RPG Room',
            'slug' => 'rpg-room',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $resource = $this->repository->findBySlug('rpg-room');

        $this->assertNotNull($resource);
        $this->assertTrue($id->equals($resource->id));
        $this->assertEquals('RPG Room', $resource->name);
    }

    public function test_it_returns_null_when_slug_not_found(): void
    {
        $resource = $this->repository->findBySlug('non-existent-slug');

        $this->assertNull($resource);
    }

    public function test_it_deletes_resource(): void
    {
        $id = BookableResourceId::generate();

        BookableResourceModel::create([
            'id' => $id->value,
            'name' => 'To Delete',
            'slug' => 'to-delete',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $this->assertDatabaseHas('venuebookings_resources', ['id' => $id->value]);

        $this->repository->delete($id);

        $this->assertDatabaseMissing('venuebookings_resources', ['id' => $id->value]);
    }

    public function test_it_saves_resource_with_null_description_and_capacity(): void
    {
        $id = BookableResourceId::generate();
        $resource = new BookableResource(
            id: $id,
            name: 'Minimal Resource',
            slug: 'minimal-resource',
            description: null,
            capacity: null,
            status: BookableResourceStatus::Active,
            sortOrder: 0,
        );

        $this->repository->save($resource);

        $this->assertDatabaseHas('venuebookings_resources', [
            'id' => $id->value,
            'description' => null,
            'capacity' => null,
        ]);

        $found = $this->repository->find($id);
        $this->assertNotNull($found);
        $this->assertNull($found->description);
        $this->assertNull($found->capacity);
    }
}
