<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Unit\Domain\ValueObjects;

use InvalidArgumentException;
use Modules\VenueBookings\Domain\ValueObjects\OperatingScheduleId;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

final class OperatingScheduleIdTest extends TestCase
{
    public function test_it_generates_valid_uuid(): void
    {
        $id = OperatingScheduleId::generate();

        $this->assertInstanceOf(OperatingScheduleId::class, $id);
        $this->assertTrue(Uuid::isValid($id->value()));
    }

    public function test_it_creates_from_string(): void
    {
        $uuid = Uuid::uuid4()->toString();

        $id = OperatingScheduleId::fromString($uuid);

        $this->assertInstanceOf(OperatingScheduleId::class, $id);
        $this->assertEquals($uuid, $id->value());
    }

    public function test_it_throws_on_invalid_uuid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid UUID: not-a-valid-uuid');

        OperatingScheduleId::fromString('not-a-valid-uuid');
    }

    public function test_it_compares_equality(): void
    {
        $uuid = Uuid::uuid4()->toString();
        $id1 = OperatingScheduleId::fromString($uuid);
        $id2 = OperatingScheduleId::fromString($uuid);
        $id3 = OperatingScheduleId::generate();

        $this->assertTrue($id1->equals($id2));
        $this->assertFalse($id1->equals($id3));
    }
}
