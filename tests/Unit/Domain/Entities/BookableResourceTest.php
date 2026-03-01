<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Unit\Domain\Entities;

use Modules\VenueBookings\Domain\Entities\BookableResource;
use Modules\VenueBookings\Domain\Enums\BookableResourceStatus;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Tests\TestCase;

final class BookableResourceTest extends TestCase
{
    public function test_it_creates_with_valid_data(): void
    {
        $id = BookableResourceId::generate();

        $resource = new BookableResource(
            id: $id,
            name: 'Main hall',
            slug: 'main-hall',
            description: 'The primary gaming hall',
            capacity: 40,
            status: BookableResourceStatus::Active,
            sortOrder: 1,
        );

        $this->assertSame($id, $resource->id);
        $this->assertEquals('Main hall', $resource->name);
        $this->assertEquals('main-hall', $resource->slug);
        $this->assertEquals('The primary gaming hall', $resource->description);
        $this->assertEquals(40, $resource->capacity);
        $this->assertEquals(BookableResourceStatus::Active, $resource->status);
        $this->assertEquals(1, $resource->sortOrder);
    }

    public function test_it_is_active_when_status_active(): void
    {
        $resource = $this->createResource(BookableResourceStatus::Active);

        $this->assertTrue($resource->isActive());
    }

    public function test_it_is_not_active_when_inactive_or_maintenance(): void
    {
        $inactive = $this->createResource(BookableResourceStatus::Inactive);
        $maintenance = $this->createResource(BookableResourceStatus::Maintenance);

        $this->assertFalse($inactive->isActive());
        $this->assertFalse($maintenance->isActive());
    }

    public function test_it_activates(): void
    {
        $resource = $this->createResource(BookableResourceStatus::Inactive);

        $resource->activate();

        $this->assertEquals(BookableResourceStatus::Active, $resource->status);
        $this->assertTrue($resource->isActive());
    }

    public function test_it_deactivates(): void
    {
        $resource = $this->createResource(BookableResourceStatus::Active);

        $resource->deactivate();

        $this->assertEquals(BookableResourceStatus::Inactive, $resource->status);
        $this->assertFalse($resource->isActive());
    }

    private function createResource(BookableResourceStatus $status): BookableResource
    {
        return new BookableResource(
            id: BookableResourceId::generate(),
            name: 'Test resource',
            slug: 'test-resource',
            description: null,
            capacity: null,
            status: $status,
        );
    }
}
