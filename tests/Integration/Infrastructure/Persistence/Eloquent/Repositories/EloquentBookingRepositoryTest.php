<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Integration\Infrastructure\Persistence\Eloquent\Repositories;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\VenueBookings\Domain\Entities\Booking;
use Modules\VenueBookings\Domain\Enums\BookingStatus;
use Modules\VenueBookings\Domain\Exceptions\BookingNotFoundException;
use Modules\VenueBookings\Domain\Repositories\BookingRepositoryInterface;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Modules\VenueBookings\Domain\ValueObjects\BookingFieldValue;
use Modules\VenueBookings\Domain\ValueObjects\BookingId;
use Modules\VenueBookings\Domain\ValueObjects\TimeRange;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Repositories\EloquentBookingRepository;
use Tests\TestCase;

final class EloquentBookingRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentBookingRepository $repository;

    private BookableResourceModel $resource;

    private UserModel $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentBookingRepository();

        $this->resource = BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Room',
            'slug' => 'test-room',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $this->user = UserModel::factory()->create();
    }

    public function test_it_implements_repository_interface(): void
    {
        $this->assertInstanceOf(BookingRepositoryInterface::class, $this->repository);
    }

    public function test_it_saves_new_booking(): void
    {
        $bookingId = BookingId::generate();
        $resourceId = BookableResourceId::fromString($this->resource->id);

        $booking = new Booking(
            id: $bookingId,
            resourceId: $resourceId,
            userId: $this->user->id,
            date: '2026-03-15',
            timeRange: new TimeRange(startTime: '10:00', endTime: '12:00'),
            status: BookingStatus::Pending,
        );

        $this->repository->save($booking);

        $this->assertDatabaseHas('venuebookings_bookings', [
            'id' => $bookingId->value,
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-15',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'pending',
        ]);
    }

    public function test_it_saves_booking_with_all_fields(): void
    {
        $bookingId = BookingId::generate();
        $resourceId = BookableResourceId::fromString($this->resource->id);
        $event = EventModel::factory()->create();

        $fieldValues = [
            new BookingFieldValue(
                fieldKey: 'game_name',
                fieldLabel: 'Game name',
                value: 'Warhammer 40K',
                visibility: 'public',
            ),
        ];

        $booking = new Booking(
            id: $bookingId,
            resourceId: $resourceId,
            userId: $this->user->id,
            date: '2026-04-01',
            timeRange: new TimeRange(startTime: '14:00', endTime: '18:00'),
            status: BookingStatus::Confirmed,
            eventId: $event->id,
            gameTableId: 'gt-123',
            tournamentId: 'tour-456',
            fieldValues: $fieldValues,
            cancellationReason: null,
            adminNotes: 'VIP booking',
            confirmedAt: new DateTimeImmutable('2026-03-28 10:00:00'),
            cancelledAt: null,
        );

        $this->repository->save($booking);

        $this->assertDatabaseHas('venuebookings_bookings', [
            'id' => $bookingId->value,
            'event_id' => $event->id,
            'game_table_id' => 'gt-123',
            'tournament_id' => 'tour-456',
            'admin_notes' => 'VIP booking',
            'status' => 'confirmed',
        ]);
    }

    public function test_it_finds_booking_by_id(): void
    {
        $bookingId = BookingId::generate();
        $resourceId = BookableResourceId::fromString($this->resource->id);

        BookingModel::create([
            'id' => $bookingId->value,
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-15',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'pending',
        ]);

        $booking = $this->repository->find($bookingId);

        $this->assertNotNull($booking);
        $this->assertTrue($bookingId->equals($booking->id));
        $this->assertTrue($resourceId->equals($booking->resourceId));
        $this->assertEquals($this->user->id, $booking->userId);
        $this->assertEquals('2026-03-15', $booking->date);
        $this->assertEquals('10:00', $booking->timeRange->startTime);
        $this->assertEquals('12:00', $booking->timeRange->endTime);
        $this->assertEquals(BookingStatus::Pending, $booking->status);
    }

    public function test_it_returns_null_when_booking_not_found(): void
    {
        $nonExistentId = BookingId::generate();

        $booking = $this->repository->find($nonExistentId);

        $this->assertNull($booking);
    }

    public function test_find_or_fail_throws_when_not_found(): void
    {
        $nonExistentId = BookingId::generate();

        $this->expectException(BookingNotFoundException::class);

        $this->repository->findOrFail($nonExistentId);
    }

    public function test_find_or_fail_returns_entity_when_found(): void
    {
        $bookingId = BookingId::generate();

        BookingModel::create([
            'id' => $bookingId->value,
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-15',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'pending',
        ]);

        $booking = $this->repository->findOrFail($bookingId);

        $this->assertTrue($bookingId->equals($booking->id));
    }

    public function test_it_gets_bookings_by_resource_and_date(): void
    {
        $resourceId = BookableResourceId::fromString($this->resource->id);

        // Booking on target date
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-15',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'pending',
        ]);

        // Another booking on target date
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-15',
            'start_time' => '14:00',
            'end_time' => '16:00',
            'status' => 'confirmed',
        ]);

        // Booking on different date
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-16',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'pending',
        ]);

        $bookings = $this->repository->getByResourceAndDate($resourceId, '2026-03-15');

        $this->assertCount(2, $bookings);
        // Ordered by start_time
        $this->assertEquals('10:00', $bookings[0]->timeRange->startTime);
        $this->assertEquals('14:00', $bookings[1]->timeRange->startTime);
    }

    public function test_it_gets_bookings_by_user(): void
    {
        $secondUser = UserModel::factory()->create();

        // Booking for first user
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-15',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'pending',
        ]);

        // Booking for first user on earlier date
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-10',
            'start_time' => '14:00',
            'end_time' => '16:00',
            'status' => 'confirmed',
        ]);

        // Booking for second user
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $secondUser->id,
            'date' => '2026-03-15',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'pending',
        ]);

        $bookings = $this->repository->getByUser($this->user->id);

        $this->assertCount(2, $bookings);
        // Ordered by date descending
        $this->assertEquals('2026-03-15', $bookings[0]->date);
        $this->assertEquals('2026-03-10', $bookings[1]->date);
    }

    public function test_it_counts_active_bookings_by_user(): void
    {
        // Pending booking (active)
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-15',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'pending',
        ]);

        // Confirmed booking (active)
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-16',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'confirmed',
        ]);

        // Cancelled booking (not active)
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-17',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'cancelled',
        ]);

        // Completed booking (not active)
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-14',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'completed',
        ]);

        $count = $this->repository->countActiveByUser($this->user->id);

        $this->assertEquals(2, $count);
    }

    public function test_it_detects_overlapping_booking(): void
    {
        $resourceId = BookableResourceId::fromString($this->resource->id);

        // Existing confirmed booking 10:00-12:00
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-15',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'confirmed',
        ]);

        // Overlapping time range 11:00-13:00
        $overlapping = new TimeRange(startTime: '11:00', endTime: '13:00');

        $result = $this->repository->hasOverlappingBooking(
            $resourceId,
            '2026-03-15',
            $overlapping,
        );

        $this->assertTrue($result);
    }

    public function test_it_does_not_detect_overlap_with_adjacent_booking(): void
    {
        $resourceId = BookableResourceId::fromString($this->resource->id);

        // Existing confirmed booking 10:00-12:00
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-15',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'confirmed',
        ]);

        // Adjacent time range 12:00-14:00 (starts when previous ends)
        $adjacent = new TimeRange(startTime: '12:00', endTime: '14:00');

        $result = $this->repository->hasOverlappingBooking(
            $resourceId,
            '2026-03-15',
            $adjacent,
        );

        $this->assertFalse($result);
    }

    public function test_it_does_not_detect_overlap_with_cancelled_booking(): void
    {
        $resourceId = BookableResourceId::fromString($this->resource->id);

        // Existing cancelled booking 10:00-12:00
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-15',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'cancelled',
        ]);

        // Same time range
        $sameTime = new TimeRange(startTime: '10:00', endTime: '12:00');

        $result = $this->repository->hasOverlappingBooking(
            $resourceId,
            '2026-03-15',
            $sameTime,
        );

        $this->assertFalse($result);
    }

    public function test_it_excludes_booking_from_overlap_check(): void
    {
        $resourceId = BookableResourceId::fromString($this->resource->id);
        $existingBookingId = BookingId::fromString((string) Str::uuid());

        // Existing confirmed booking 10:00-12:00
        BookingModel::create([
            'id' => $existingBookingId->value,
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-15',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'confirmed',
        ]);

        // Same time range but excluding the existing booking (e.g., when updating)
        $sameTime = new TimeRange(startTime: '10:00', endTime: '12:00');

        $result = $this->repository->hasOverlappingBooking(
            $resourceId,
            '2026-03-15',
            $sameTime,
            excludeBookingId: $existingBookingId,
        );

        $this->assertFalse($result);
    }

    public function test_it_does_not_detect_overlap_on_different_date(): void
    {
        $resourceId = BookableResourceId::fromString($this->resource->id);

        // Existing confirmed booking on March 15
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-15',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'confirmed',
        ]);

        // Same time range but different date
        $timeRange = new TimeRange(startTime: '10:00', endTime: '12:00');

        $result = $this->repository->hasOverlappingBooking(
            $resourceId,
            '2026-03-16',
            $timeRange,
        );

        $this->assertFalse($result);
    }

    public function test_it_gets_bookings_by_resource_in_date_range(): void
    {
        $resourceId = BookableResourceId::fromString($this->resource->id);

        // Booking within range
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-15',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'pending',
        ]);

        // Booking within range
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-20',
            'start_time' => '14:00',
            'end_time' => '16:00',
            'status' => 'confirmed',
        ]);

        // Booking outside range
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-04-01',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'pending',
        ]);

        $bookings = $this->repository->getByResourceInDateRange(
            $resourceId,
            '2026-03-10',
            '2026-03-25',
        );

        $this->assertCount(2, $bookings);
        // Ordered by date then start_time
        $this->assertEquals('2026-03-15', $bookings[0]->date);
        $this->assertEquals('2026-03-20', $bookings[1]->date);
    }

    public function test_it_excludes_non_active_bookings_from_date_range(): void
    {
        $resourceId = BookableResourceId::fromString($this->resource->id);

        // Active: pending
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-15',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'pending',
        ]);

        // Active: confirmed
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-16',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'confirmed',
        ]);

        // Non-active: cancelled (should be excluded)
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-17',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'cancelled',
        ]);

        // Non-active: rejected (should be excluded)
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-18',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'rejected',
        ]);

        // Non-active: no_show (should be excluded)
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-19',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'no_show',
        ]);

        // Non-active: completed (should be excluded)
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-20',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'completed',
        ]);

        $bookings = $this->repository->getByResourceInDateRange(
            $resourceId,
            '2026-03-10',
            '2026-03-25',
        );

        $this->assertCount(2, $bookings);
        $this->assertEquals(BookingStatus::Pending, $bookings[0]->status);
        $this->assertEquals(BookingStatus::Confirmed, $bookings[1]->status);
    }

    public function test_it_gets_bookings_by_status(): void
    {
        // Pending booking
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-15',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'pending',
        ]);

        // Confirmed booking
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-16',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'confirmed',
        ]);

        // Another pending booking
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-17',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'pending',
        ]);

        $pendingBookings = $this->repository->getByStatus(BookingStatus::Pending);

        $this->assertCount(2, $pendingBookings);

        $confirmedBookings = $this->repository->getByStatus(BookingStatus::Confirmed);

        $this->assertCount(1, $confirmedBookings);
    }

    public function test_it_gets_confirmed_bookings_for_reminder(): void
    {
        // Confirmed booking in reminder window
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-15',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'confirmed',
        ]);

        // Confirmed booking outside window
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-04-01',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'confirmed',
        ]);

        // Pending booking in window (should be excluded)
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-15',
            'start_time' => '14:00',
            'end_time' => '16:00',
            'status' => 'pending',
        ]);

        $bookings = $this->repository->getConfirmedBookingsForReminder(
            new DateTimeImmutable('2026-03-14'),
            new DateTimeImmutable('2026-03-16'),
        );

        $this->assertCount(1, $bookings);
        $this->assertEquals(BookingStatus::Confirmed, $bookings[0]->status);
        $this->assertEquals('2026-03-15', $bookings[0]->date);
    }

    public function test_it_deletes_booking(): void
    {
        $bookingId = BookingId::generate();

        BookingModel::create([
            'id' => $bookingId->value,
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-15',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('venuebookings_bookings', ['id' => $bookingId->value]);

        $this->repository->delete($bookingId);

        $this->assertDatabaseMissing('venuebookings_bookings', ['id' => $bookingId->value]);
    }

    public function test_it_roundtrips_booking_with_field_values(): void
    {
        $bookingId = BookingId::generate();
        $resourceId = BookableResourceId::fromString($this->resource->id);

        $fieldValues = [
            new BookingFieldValue(
                fieldKey: 'game_name',
                fieldLabel: 'Game name',
                value: 'Warhammer 40K',
                visibility: 'public',
            ),
            new BookingFieldValue(
                fieldKey: 'player_count',
                fieldLabel: 'Number of players',
                value: '4',
                visibility: 'admin',
            ),
        ];

        $booking = new Booking(
            id: $bookingId,
            resourceId: $resourceId,
            userId: $this->user->id,
            date: '2026-03-15',
            timeRange: new TimeRange(startTime: '10:00', endTime: '12:00'),
            status: BookingStatus::Pending,
            fieldValues: $fieldValues,
        );

        $this->repository->save($booking);

        $found = $this->repository->find($bookingId);

        $this->assertNotNull($found);
        $this->assertCount(2, $found->fieldValues);
        $this->assertEquals('game_name', $found->fieldValues[0]->fieldKey);
        $this->assertEquals('Warhammer 40K', $found->fieldValues[0]->value);
        $this->assertEquals('player_count', $found->fieldValues[1]->fieldKey);
    }

    public function test_it_roundtrips_booking_with_timestamps(): void
    {
        $bookingId = BookingId::generate();
        $resourceId = BookableResourceId::fromString($this->resource->id);

        $booking = new Booking(
            id: $bookingId,
            resourceId: $resourceId,
            userId: $this->user->id,
            date: '2026-03-15',
            timeRange: new TimeRange(startTime: '10:00', endTime: '12:00'),
            status: BookingStatus::Confirmed,
            confirmedAt: new DateTimeImmutable('2026-03-14 15:30:00'),
        );

        $this->repository->save($booking);

        $found = $this->repository->find($bookingId);

        $this->assertNotNull($found);
        $this->assertNotNull($found->confirmedAt);
        $this->assertEquals('2026-03-14 15:30:00', $found->confirmedAt->format('Y-m-d H:i:s'));
    }
}
