<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Unit\Domain\Entities;

use DateTimeImmutable;
use DateTimeZone;
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

    public function test_it_starts_at_its_date_and_start_time_in_the_venue_timezone(): void
    {
        $booking = $this->createBooking(date: '2026-03-15', startTime: '10:00');

        $this->assertSame(
            '2026-03-15T10:00:00+01:00',
            $booking->startsAt(new DateTimeZone('Europe/Madrid'))->format(DATE_ATOM),
        );
    }

    public function test_its_start_follows_daylight_saving_time_in_the_venue_timezone(): void
    {
        $madrid = new DateTimeZone('Europe/Madrid');

        // Madrid moves its clocks forward on 2026-03-29 and back on 2026-10-25
        $this->assertSame('2026-03-28T10:00:00+01:00', $this->createBooking(date: '2026-03-28')->startsAt($madrid)->format(DATE_ATOM));
        $this->assertSame('2026-03-29T10:00:00+02:00', $this->createBooking(date: '2026-03-29')->startsAt($madrid)->format(DATE_ATOM));
        $this->assertSame('2026-10-24T10:00:00+02:00', $this->createBooking(date: '2026-10-24')->startsAt($madrid)->format(DATE_ATOM));
        $this->assertSame('2026-10-25T10:00:00+01:00', $this->createBooking(date: '2026-10-25')->startsAt($madrid)->format(DATE_ATOM));
    }

    public function test_its_start_accepts_times_with_seconds(): void
    {
        // PostgreSQL time columns come back as HH:MM:SS
        $booking = $this->createBooking(date: '2026-03-15', startTime: '10:00:00', endTime: '12:00:00');

        $this->assertSame(
            '2026-03-15T10:00:00+01:00',
            $booking->startsAt(new DateTimeZone('Europe/Madrid'))->format(DATE_ATOM),
        );
    }

    public function test_it_has_started_from_its_start_time_on(): void
    {
        $madrid = new DateTimeZone('Europe/Madrid');
        $booking = $this->createBooking(date: '2026-03-15', startTime: '10:00');

        $this->assertFalse($booking->hasStarted(new DateTimeImmutable('2026-03-15 09:59:59', $madrid), $madrid));
        $this->assertTrue($booking->hasStarted(new DateTimeImmutable('2026-03-15 10:00:00', $madrid), $madrid));
        $this->assertTrue($booking->hasStarted(new DateTimeImmutable('2026-03-15 10:00:01', $madrid), $madrid));
        $this->assertTrue($booking->hasStarted(new DateTimeImmutable('2026-03-16 08:00:00', $madrid), $madrid));
    }

    public function test_it_compares_instants_whatever_the_timezone_of_now(): void
    {
        $madrid = new DateTimeZone('Europe/Madrid');
        $utc = new DateTimeZone('UTC');

        // 10:00 in Madrid on 2026-03-29 (summer time, UTC+2) is 08:00 UTC
        $booking = $this->createBooking(date: '2026-03-29', startTime: '10:00');
        $this->assertFalse($booking->hasStarted(new DateTimeImmutable('2026-03-29 07:59:59', $utc), $madrid));
        $this->assertTrue($booking->hasStarted(new DateTimeImmutable('2026-03-29 08:00:00', $utc), $madrid));

        // 10:00 in Madrid on 2026-10-25 (winter time again, UTC+1) is 09:00 UTC
        $booking = $this->createBooking(date: '2026-10-25', startTime: '10:00');
        $this->assertFalse($booking->hasStarted(new DateTimeImmutable('2026-10-25 08:59:59', $utc), $madrid));
        $this->assertTrue($booking->hasStarted(new DateTimeImmutable('2026-10-25 09:00:00', $utc), $madrid));
    }

    public function test_a_booking_that_crosses_midnight_starts_on_its_own_date(): void
    {
        $madrid = new DateTimeZone('Europe/Madrid');
        $booking = $this->createBooking(date: '2026-03-15', startTime: '22:00', endTime: '02:00');

        $this->assertFalse($booking->hasStarted(new DateTimeImmutable('2026-03-15 21:59:59', $madrid), $madrid));
        $this->assertTrue($booking->hasStarted(new DateTimeImmutable('2026-03-15 22:00:00', $madrid), $madrid));
        $this->assertTrue($booking->hasStarted(new DateTimeImmutable('2026-03-16 01:00:00', $madrid), $madrid));
    }

    public function test_being_cancellable_still_depends_only_on_the_status(): void
    {
        // The panel cancels at any time; the start cutoff lives in the website path
        $booking = $this->createBooking(date: '2020-01-01');

        $this->assertTrue($booking->isCancellable());
    }

    public function test_it_ends_at_its_date_and_end_time_in_the_venue_timezone(): void
    {
        $booking = $this->createBooking(date: '2026-03-15', startTime: '10:00', endTime: '12:00');

        $this->assertSame('2026-03-15T12:00:00+01:00', $booking->endsAt(new DateTimeZone('Europe/Madrid'))->format(DATE_ATOM));
    }

    public function test_a_booking_that_crosses_midnight_ends_the_next_day(): void
    {
        $booking = $this->createBooking(date: '2026-03-15', startTime: '22:00', endTime: '02:00');

        $this->assertSame('2026-03-16T02:00:00+01:00', $booking->endsAt(new DateTimeZone('Europe/Madrid'))->format(DATE_ATOM));
    }

    public function test_its_end_accepts_times_with_seconds(): void
    {
        $booking = $this->createBooking(date: '2026-03-15', startTime: '10:00:00', endTime: '12:00:00');

        $this->assertSame('2026-03-15T12:00:00+01:00', $booking->endsAt(new DateTimeZone('Europe/Madrid'))->format(DATE_ATOM));
    }

    private function createBooking(
        ?string $userId = null,
        string $date = '2026-03-15',
        string $startTime = '10:00',
        string $endTime = '12:00',
    ): Booking {
        return new Booking(
            id: BookingId::generate(),
            resourceId: BookableResourceId::generate(),
            userId: $userId ?? Uuid::uuid4()->toString(),
            date: $date,
            timeRange: new TimeRange($startTime, $endTime),
        );
    }
}
