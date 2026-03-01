<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Unit\Domain\ValueObjects;

use InvalidArgumentException;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

final class BookableResourceIdTest extends TestCase
{
    public function test_it_generates_valid_uuid(): void
    {
        $id = BookableResourceId::generate();

        $this->assertInstanceOf(BookableResourceId::class, $id);
        $this->assertTrue(Uuid::isValid($id->value()));
    }

    public function test_it_creates_from_string(): void
    {
        $uuid = Uuid::uuid4()->toString();

        $id = BookableResourceId::fromString($uuid);

        $this->assertInstanceOf(BookableResourceId::class, $id);
        $this->assertEquals($uuid, $id->value());
    }

    public function test_it_throws_on_invalid_uuid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid UUID: not-a-valid-uuid');

        BookableResourceId::fromString('not-a-valid-uuid');
    }

    public function test_it_compares_equality(): void
    {
        $uuid = Uuid::uuid4()->toString();
        $id1 = BookableResourceId::fromString($uuid);
        $id2 = BookableResourceId::fromString($uuid);
        $id3 = BookableResourceId::generate();

        $this->assertTrue($id1->equals($id2));
        $this->assertFalse($id1->equals($id3));
    }
}
