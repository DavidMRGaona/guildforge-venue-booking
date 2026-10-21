<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Unit\Application\DTOs\Response;

use Modules\VenueBookings\Application\DTOs\Response\BookingCalendarEventDTO;
use Modules\VenueBookings\Domain\Entities\Booking;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Modules\VenueBookings\Domain\ValueObjects\BookingId;
use Modules\VenueBookings\Domain\ValueObjects\TimeRange;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

final class BookingCalendarEventDTOTest extends TestCase
{
    public function test_it_ends_on_the_same_day(): void
    {
        $dto = BookingCalendarEventDTO::fromEntity($this->createBooking('2026-10-22', '14:00', '20:00'));

        $this->assertSame('2026-10-22T14:00', $dto->start);
        $this->assertSame('2026-10-22T20:00', $dto->end);
    }

    public function test_a_booking_that_crosses_midnight_ends_on_the_next_day(): void
    {
        $dto = BookingCalendarEventDTO::fromEntity($this->createBooking('2026-10-22', '20:00', '01:00'));

        $this->assertSame('2026-10-22T20:00', $dto->start);
        $this->assertSame('2026-10-23T01:00', $dto->end);
    }

    public function test_a_booking_that_crosses_midnight_on_new_years_eve_ends_next_year(): void
    {
        $dto = BookingCalendarEventDTO::fromEntity($this->createBooking('2026-12-31', '22:00', '02:00'));

        $this->assertSame('2027-01-01T02:00', $dto->end);
    }

    private function createBooking(string $date, string $startTime, string $endTime): Booking
    {
        return new Booking(
            id: BookingId::generate(),
            resourceId: BookableResourceId::generate(),
            userId: Uuid::uuid4()->toString(),
            date: $date,
            timeRange: new TimeRange($startTime, $endTime),
        );
    }
}
