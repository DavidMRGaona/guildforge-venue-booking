<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\VenueBookings\Application\DTOs\CancelBookingDTO;
use Modules\VenueBookings\Application\DTOs\CreateBookingDTO;
use Modules\VenueBookings\Domain\Enums\BookingStatus;
use Modules\VenueBookings\Domain\Events\BookingCancelled;
use Modules\VenueBookings\Domain\Events\BookingCompleted;
use Modules\VenueBookings\Domain\Events\BookingConfirmed;
use Modules\VenueBookings\Domain\Events\BookingCreated;
use Modules\VenueBookings\Domain\Events\BookingNoShow;
use Modules\VenueBookings\Domain\Events\BookingRejected;
use Modules\VenueBookings\Domain\Exceptions\MinimumSlotsNotMetException;
use Modules\VenueBookings\Domain\Exceptions\SlotUnavailableException;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\OperatingScheduleModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Repositories\EloquentBookingRepository;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Repositories\EloquentOperatingScheduleRepository;
use Modules\VenueBookings\Infrastructure\Services\BookingService;
use Modules\VenueBookings\Infrastructure\Services\BookingSettingsReader;
use Modules\VenueBookings\Infrastructure\Services\SlotAvailabilityService;
use Tests\TestCase;

final class BookingServiceTest extends TestCase
{
    use RefreshDatabase;

    private BookingService $service;

    private EloquentBookingRepository $bookingRepository;

    private BookableResourceModel $resource;

    private UserModel $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bookingRepository = new EloquentBookingRepository;
        $slotAvailabilityService = new SlotAvailabilityService(
            new EloquentOperatingScheduleRepository,
            $this->bookingRepository,
            new BookingSettingsReader,
        );

        $scheduleRepository = new EloquentOperatingScheduleRepository;

        $this->service = new BookingService(
            $this->bookingRepository,
            $slotAvailabilityService,
            new BookingSettingsReader,
            $scheduleRepository,
        );

        $this->resource = BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Room',
            'slug' => 'test-room',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $this->user = UserModel::factory()->create();
    }

    public function test_it_creates_booking_with_confirmed_status_when_auto_confirm(): void
    {
        Event::fake();
        config()->set('venue-bookings.approval_mode', 'auto_confirm');

        $dto = new CreateBookingDTO(
            resourceId: $this->resource->id,
            userId: $this->user->id,
            date: '2026-03-16',
            startTime: '10:00',
            endTime: '12:00',
        );

        $result = $this->service->createBooking($dto);

        $this->assertEquals(BookingStatus::Confirmed, $result->status);
        $this->assertNotNull($result->confirmedAt);
        $this->assertDatabaseHas('venuebookings_bookings', [
            'id' => $result->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_it_creates_booking_with_pending_status_when_require_approval(): void
    {
        Event::fake();
        config()->set('venue-bookings.approval_mode', 'require_approval');

        $dto = new CreateBookingDTO(
            resourceId: $this->resource->id,
            userId: $this->user->id,
            date: '2026-03-16',
            startTime: '10:00',
            endTime: '12:00',
        );

        $result = $this->service->createBooking($dto);

        $this->assertEquals(BookingStatus::Pending, $result->status);
        $this->assertNull($result->confirmedAt);
        $this->assertDatabaseHas('venuebookings_bookings', [
            'id' => $result->id,
            'status' => 'pending',
        ]);
    }

    public function test_it_throws_slot_unavailable_exception_when_slot_is_taken(): void
    {
        Event::fake();
        config()->set('venue-bookings.approval_mode', 'auto_confirm');

        // Create an existing booking at the same time
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-16',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'confirmed',
        ]);

        $dto = new CreateBookingDTO(
            resourceId: $this->resource->id,
            userId: $this->user->id,
            date: '2026-03-16',
            startTime: '11:00',
            endTime: '13:00',
        );

        $this->expectException(SlotUnavailableException::class);

        $this->service->createBooking($dto);
    }

    public function test_it_dispatches_booking_created_event(): void
    {
        Event::fake();
        config()->set('venue-bookings.approval_mode', 'require_approval');

        $dto = new CreateBookingDTO(
            resourceId: $this->resource->id,
            userId: $this->user->id,
            date: '2026-03-16',
            startTime: '10:00',
            endTime: '12:00',
        );

        $this->service->createBooking($dto);

        Event::assertDispatched(BookingCreated::class, function (BookingCreated $event): bool {
            return $event->resourceId === $this->resource->id
                && $event->userId === $this->user->id
                && $event->date === '2026-03-16'
                && $event->startTime === '10:00'
                && $event->endTime === '12:00';
        });
    }

    public function test_it_dispatches_booking_confirmed_event_when_auto_confirm(): void
    {
        Event::fake();
        config()->set('venue-bookings.approval_mode', 'auto_confirm');

        $dto = new CreateBookingDTO(
            resourceId: $this->resource->id,
            userId: $this->user->id,
            date: '2026-03-16',
            startTime: '10:00',
            endTime: '12:00',
        );

        $this->service->createBooking($dto);

        Event::assertDispatched(BookingCreated::class);
        Event::assertDispatched(BookingConfirmed::class, function (BookingConfirmed $event): bool {
            return $event->userId === $this->user->id;
        });
    }

    public function test_it_does_not_dispatch_confirmed_event_when_require_approval(): void
    {
        Event::fake();
        config()->set('venue-bookings.approval_mode', 'require_approval');

        $dto = new CreateBookingDTO(
            resourceId: $this->resource->id,
            userId: $this->user->id,
            date: '2026-03-16',
            startTime: '10:00',
            endTime: '12:00',
        );

        $this->service->createBooking($dto);

        Event::assertDispatched(BookingCreated::class);
        Event::assertNotDispatched(BookingConfirmed::class);
    }

    public function test_it_confirms_booking(): void
    {
        Event::fake();

        $bookingId = (string) Str::uuid();
        BookingModel::create([
            'id' => $bookingId,
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-16',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'pending',
        ]);

        $result = $this->service->confirmBooking($bookingId);

        $this->assertEquals(BookingStatus::Confirmed, $result->status);
        $this->assertNotNull($result->confirmedAt);
        $this->assertDatabaseHas('venuebookings_bookings', [
            'id' => $bookingId,
            'status' => 'confirmed',
        ]);

        Event::assertDispatched(BookingConfirmed::class, function (BookingConfirmed $event) use ($bookingId): bool {
            return $event->bookingId === $bookingId
                && $event->userId === $this->user->id;
        });
    }

    public function test_it_cancels_booking(): void
    {
        Event::fake();

        $bookingId = (string) Str::uuid();
        BookingModel::create([
            'id' => $bookingId,
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-16',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'confirmed',
        ]);

        $dto = new CancelBookingDTO(
            bookingId: $bookingId,
            userId: $this->user->id,
            reason: 'Schedule conflict',
        );

        $result = $this->service->cancelBooking($dto);

        $this->assertEquals(BookingStatus::Cancelled, $result->status);
        $this->assertEquals('Schedule conflict', $result->cancellationReason);
        $this->assertDatabaseHas('venuebookings_bookings', [
            'id' => $bookingId,
            'status' => 'cancelled',
            'cancellation_reason' => 'Schedule conflict',
        ]);

        Event::assertDispatched(BookingCancelled::class, function (BookingCancelled $event) use ($bookingId): bool {
            return $event->bookingId === $bookingId
                && $event->userId === $this->user->id
                && $event->reason === 'Schedule conflict'
                && $event->wasConfirmed === true;
        });
    }

    public function test_it_rejects_booking(): void
    {
        Event::fake();

        $bookingId = (string) Str::uuid();
        BookingModel::create([
            'id' => $bookingId,
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-16',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'pending',
        ]);

        $result = $this->service->rejectBooking($bookingId, 'Resource not suitable');

        $this->assertEquals(BookingStatus::Rejected, $result->status);
        $this->assertEquals('Resource not suitable', $result->cancellationReason);
        $this->assertDatabaseHas('venuebookings_bookings', [
            'id' => $bookingId,
            'status' => 'rejected',
        ]);

        Event::assertDispatched(BookingRejected::class, function (BookingRejected $event) use ($bookingId): bool {
            return $event->bookingId === $bookingId
                && $event->userId === $this->user->id
                && $event->reason === 'Resource not suitable';
        });
    }

    public function test_it_marks_booking_as_completed(): void
    {
        Event::fake();

        $bookingId = (string) Str::uuid();
        BookingModel::create([
            'id' => $bookingId,
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-16',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'confirmed',
        ]);

        $result = $this->service->markCompleted($bookingId);

        $this->assertEquals(BookingStatus::Completed, $result->status);
        $this->assertDatabaseHas('venuebookings_bookings', [
            'id' => $bookingId,
            'status' => 'completed',
        ]);

        Event::assertDispatched(BookingCompleted::class, function (BookingCompleted $event) use ($bookingId): bool {
            return $event->bookingId === $bookingId
                && $event->userId === $this->user->id;
        });
    }

    public function test_it_throws_when_booking_has_fewer_than_minimum_consecutive_slots(): void
    {
        Event::fake();
        config()->set('venue-bookings.approval_mode', 'auto_confirm');

        // Create schedule with min_consecutive_slots = 3
        OperatingScheduleModel::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'resource_id' => $this->resource->id,
            'slot_duration_minutes' => 60,
            'min_consecutive_slots' => 3,
            'max_consecutive_slots' => 6,
            'day_schedules' => [
                ['day_of_week' => 1, 'open_time' => '10:00', 'close_time' => '20:00', 'is_enabled' => true],
            ],
        ]);

        // Try to book only 1 slot (10:00-11:00) — should fail because min is 3
        $dto = new CreateBookingDTO(
            resourceId: $this->resource->id,
            userId: $this->user->id,
            date: '2026-03-16',
            startTime: '10:00',
            endTime: '11:00',
        );

        $this->expectException(MinimumSlotsNotMetException::class);

        $this->service->createBooking($dto);
    }

    public function test_it_allows_booking_when_meeting_minimum_consecutive_slots(): void
    {
        Event::fake();
        config()->set('venue-bookings.approval_mode', 'auto_confirm');

        // Create schedule with min_consecutive_slots = 2
        OperatingScheduleModel::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'resource_id' => $this->resource->id,
            'slot_duration_minutes' => 60,
            'min_consecutive_slots' => 2,
            'max_consecutive_slots' => 6,
            'day_schedules' => [
                ['day_of_week' => 1, 'open_time' => '10:00', 'close_time' => '20:00', 'is_enabled' => true],
            ],
        ]);

        // Book 2 slots (10:00-12:00) — exactly meets minimum
        $dto = new CreateBookingDTO(
            resourceId: $this->resource->id,
            userId: $this->user->id,
            date: '2026-03-16',
            startTime: '10:00',
            endTime: '12:00',
        );

        $result = $this->service->createBooking($dto);

        $this->assertEquals(BookingStatus::Confirmed, $result->status);
    }

    public function test_it_marks_booking_as_no_show(): void
    {
        Event::fake();

        $bookingId = (string) Str::uuid();
        BookingModel::create([
            'id' => $bookingId,
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-16',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'confirmed',
        ]);

        $result = $this->service->markNoShow($bookingId);

        $this->assertEquals(BookingStatus::NoShow, $result->status);
        $this->assertDatabaseHas('venuebookings_bookings', [
            'id' => $bookingId,
            'status' => 'no_show',
        ]);

        Event::assertDispatched(BookingNoShow::class, function (BookingNoShow $event) use ($bookingId): bool {
            return $event->bookingId === $bookingId
                && $event->userId === $this->user->id;
        });
    }
}
