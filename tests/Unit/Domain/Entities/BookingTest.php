<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Unit\Domain\Entities;

use DateTimeImmutable;
use Modules\VenueBookings\Domain\Entities\Booking;
use Modules\VenueBookings\Domain\Enums\BookingStatus;
use Modules\VenueBookings\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Modules\VenueBookings\Domain\ValueObjects\BookingId;
use Modules\VenueBookings\Domain\ValueObjects\TimeRange;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

final class BookingTest extends TestCase
{
    public function test_it_creates_with_default_pending_status(): void
    {
        $booking = $this->createBooking();

        $this->assertEquals(BookingStatus::Pending, $booking->status);
        $this->assertNull($booking->confirmedAt);
        $this->assertNull($booking->cancelledAt);
        $this->assertNull($booking->cancellationReason);
        $this->assertNull($booking->adminNotes);
    }

    public function test_it_confirms_pending_booking(): void
    {
        $booking = $this->createBooking();

        $booking->confirm();

        $this->assertEquals(BookingStatus::Confirmed, $booking->status);
        $this->assertInstanceOf(DateTimeImmutable::class, $booking->confirmedAt);
    }

    public function test_it_throws_on_confirm_when_not_pending(): void
    {
        $booking = $this->createBooking();
        $booking->confirm();

        $this->expectException(InvalidStatusTransitionException::class);

        $booking->confirm();
    }

    public function test_it_cancels_pending_booking(): void
    {
        $booking = $this->createBooking();

        $booking->cancel('User requested cancellation');

        $this->assertEquals(BookingStatus::Cancelled, $booking->status);
        $this->assertEquals('User requested cancellation', $booking->cancellationReason);
        $this->assertInstanceOf(DateTimeImmutable::class, $booking->cancelledAt);
    }

    public function test_it_cancels_confirmed_booking(): void
    {
        $booking = $this->createBooking();
        $booking->confirm();

        $booking->cancel('Schedule conflict');

        $this->assertEquals(BookingStatus::Cancelled, $booking->status);
        $this->assertEquals('Schedule conflict', $booking->cancellationReason);
        $this->assertInstanceOf(DateTimeImmutable::class, $booking->cancelledAt);
    }

    public function test_it_throws_on_cancel_when_completed(): void
    {
        $booking = $this->createBooking();
        $booking->confirm();
        $booking->markCompleted();

        $this->expectException(InvalidStatusTransitionException::class);

        $booking->cancel('Too late');
    }

    public function test_it_rejects_pending_booking(): void
    {
        $booking = $this->createBooking();

        $booking->reject('Resource unavailable');

        $this->assertEquals(BookingStatus::Rejected, $booking->status);
        $this->assertEquals('Resource unavailable', $booking->cancellationReason);
    }

    public function test_it_throws_on_reject_when_not_pending(): void
    {
        $booking = $this->createBooking();
        $booking->confirm();

        $this->expectException(InvalidStatusTransitionException::class);

        $booking->reject('Too late');
    }

    public function test_it_marks_confirmed_booking_completed(): void
    {
        $booking = $this->createBooking();
        $booking->confirm();

        $booking->markCompleted();

        $this->assertEquals(BookingStatus::Completed, $booking->status);
    }

    public function test_it_throws_on_complete_when_not_confirmed(): void
    {
        $booking = $this->createBooking();

        $this->expectException(InvalidStatusTransitionException::class);

        $booking->markCompleted();
    }

    public function test_it_marks_confirmed_booking_no_show(): void
    {
        $booking = $this->createBooking();
        $booking->confirm();

        $booking->markNoShow();

        $this->assertEquals(BookingStatus::NoShow, $booking->status);
    }

    public function test_it_throws_on_no_show_when_not_confirmed(): void
    {
        $booking = $this->createBooking();

        $this->expectException(InvalidStatusTransitionException::class);

        $booking->markNoShow();
    }

    public function test_it_is_cancellable_when_pending_or_confirmed(): void
    {
        $pending = $this->createBooking();
        $this->assertTrue($pending->isCancellable());

        $confirmed = $this->createBooking();
        $confirmed->confirm();
        $this->assertTrue($confirmed->isCancellable());
    }

    public function test_it_is_not_cancellable_when_final(): void
    {
        $completed = $this->createBooking();
        $completed->confirm();
        $completed->markCompleted();
        $this->assertFalse($completed->isCancellable());

        $cancelled = $this->createBooking();
        $cancelled->cancel();
        $this->assertFalse($cancelled->isCancellable());

        $rejected = $this->createBooking();
        $rejected->reject();
        $this->assertFalse($rejected->isCancellable());

        $noShow = $this->createBooking();
        $noShow->confirm();
        $noShow->markNoShow();
        $this->assertFalse($noShow->isCancellable());
    }

    public function test_it_belongs_to_user(): void
    {
        $userId = Uuid::uuid4()->toString();
        $booking = $this->createBooking(userId: $userId);

        $this->assertTrue($booking->belongsTo($userId));
    }

    public function test_it_does_not_belong_to_different_user(): void
    {
        $booking = $this->createBooking(userId: Uuid::uuid4()->toString());

        $this->assertFalse($booking->belongsTo(Uuid::uuid4()->toString()));
    }

    private function createBooking(?string $userId = null): Booking
    {
        return new Booking(
            id: BookingId::generate(),
            resourceId: BookableResourceId::generate(),
            userId: $userId ?? Uuid::uuid4()->toString(),
            date: '2026-03-15',
            timeRange: new TimeRange('10:00', '12:00'),
        );
    }
}
