<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Integration\Infrastructure\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\VenueBookings\Application\DTOs\CancelBookingDTO;
use Modules\VenueBookings\Application\DTOs\CreateBookingDTO;
use Modules\VenueBookings\Application\Services\BookingLockInterface;
use Modules\VenueBookings\Domain\Enums\BookingStatus;
use Modules\VenueBookings\Domain\Events\BookingCancelled;
use Modules\VenueBookings\Domain\Events\BookingCompleted;
use Modules\VenueBookings\Domain\Events\BookingConfirmed;
use Modules\VenueBookings\Domain\Events\BookingCreated;
use Modules\VenueBookings\Domain\Events\BookingNoShow;
use Modules\VenueBookings\Domain\Events\BookingRejected;
use Modules\VenueBookings\Domain\Exceptions\BookableResourceUnavailableException;
use Modules\VenueBookings\Domain\Exceptions\BookingAlreadyStartedException;
use Modules\VenueBookings\Domain\Exceptions\BookingBusyException;
use Modules\VenueBookings\Domain\Exceptions\BookingCancellationNotAllowedException;
use Modules\VenueBookings\Domain\Exceptions\BookingLimitExceededException;
use Modules\VenueBookings\Domain\Exceptions\MaximumSlotsExceededException;
use Modules\VenueBookings\Domain\Exceptions\MinimumSlotsNotMetException;
use Modules\VenueBookings\Domain\Exceptions\SlotOutsideScheduleException;
use Modules\VenueBookings\Domain\Exceptions\SlotUnavailableException;
use Modules\VenueBookings\Domain\Exceptions\TooFarToBookException;
use Modules\VenueBookings\Domain\Exceptions\TooSoonToBookException;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\OperatingScheduleModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Repositories\EloquentBookableResourceRepository;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Repositories\EloquentBookingRepository;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Repositories\EloquentOperatingScheduleRepository;
use Modules\VenueBookings\Infrastructure\Services\BookingRules;
use Modules\VenueBookings\Infrastructure\Services\BookingService;
use Modules\VenueBookings\Infrastructure\Services\BookingSettingsReader;
use Modules\VenueBookings\Infrastructure\Services\CacheBookingLock;
use Modules\VenueBookings\Infrastructure\Services\SlotAvailabilityService;
use Modules\VenueBookings\Tests\Support\OpensResourceEveryDay;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\TestCase;

final class BookingServiceTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use OpensResourceEveryDay;
    use RefreshDatabase;

    private const string VENUE_TIMEZONE = 'Europe/Madrid';

    private BookingService $service;

    private EloquentBookingRepository $bookingRepository;

    private BookableResourceModel $resource;

    private UserModel $user;

    protected function setUp(): void
    {
        parent::setUp();

        // The fixtures book 2026-03-16: freeze the clock before that day, in the venue timezone
        config()->set('venue-bookings.timezone', self::VENUE_TIMEZONE);
        $this->travelTo(CarbonImmutable::create(2026, 3, 1, 9, 0, 0, self::VENUE_TIMEZONE));

        $this->bookingRepository = new EloquentBookingRepository;
        $this->service = $this->makeService(new CacheBookingLock);

        $this->resource = BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Room',
            'slug' => 'test-room',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $this->user = UserModel::factory()->create();

        // The room opens every day 08:00-22:00 in hourly slots, so the fixtures fit its schedule
        $this->openEveryDay($this->resource->id, '08:00', '22:00', maxConsecutiveSlots: 6);
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
        $this->grant($this->user, ['venuebookings:bookings.cancel_own']);

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

        $this->useSchedule(
            [['day_of_week' => 1, 'open_time' => '10:00', 'close_time' => '20:00', 'is_enabled' => true]],
            minConsecutive: 3,
        );

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

        $this->useSchedule(
            [['day_of_week' => 1, 'open_time' => '10:00', 'close_time' => '20:00', 'is_enabled' => true]],
            minConsecutive: 2,
        );

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

    public function test_it_refuses_to_cancel_another_users_booking(): void
    {
        $bookingId = $this->createConfirmedBookingOf($this->user);
        $stranger = UserModel::factory()->create();

        try {
            $this->service->cancelBooking(new CancelBookingDTO(bookingId: $bookingId, userId: $stranger->id));
            $this->fail('Another user\'s booking was cancelled.');
        } catch (BookingCancellationNotAllowedException) {
            $this->assertDatabaseHas('venuebookings_bookings', ['id' => $bookingId, 'status' => 'confirmed']);
        }
    }

    public function test_a_user_allowed_to_cancel_any_booking_can_cancel_another_users_booking(): void
    {
        $bookingId = $this->createConfirmedBookingOf($this->user);
        $manager = $this->editorWithPermissions(['venuebookings:bookings.cancel_any']);

        $result = $this->service->cancelBooking(new CancelBookingDTO(bookingId: $bookingId, userId: $manager->id));

        $this->assertEquals(BookingStatus::Cancelled, $result->status);
    }

    public function test_staff_cancellation_does_not_depend_on_ownership(): void
    {
        $bookingId = $this->createConfirmedBookingOf($this->user);
        $staff = UserModel::factory()->create();

        $result = $this->service->cancelBookingAsStaff(new CancelBookingDTO(bookingId: $bookingId, userId: $staff->id));

        $this->assertEquals(BookingStatus::Cancelled, $result->status);
    }

    public function test_an_owner_without_cancel_own_cannot_cancel_their_booking(): void
    {
        $bookingId = $this->createConfirmedBookingOf($this->user);

        try {
            $this->service->cancelBooking(new CancelBookingDTO(bookingId: $bookingId, userId: $this->user->id));
            $this->fail('The booking was cancelled without cancel_own.');
        } catch (BookingCancellationNotAllowedException) {
            $this->assertDatabaseHas('venuebookings_bookings', ['id' => $bookingId, 'status' => 'confirmed']);
        }
    }

    public function test_a_user_with_cancel_own_cannot_cancel_another_users_booking(): void
    {
        $bookingId = $this->createConfirmedBookingOf($this->user);
        $stranger = UserModel::factory()->create();
        $this->grant($stranger, ['venuebookings:bookings.cancel_own']);

        $this->expectException(BookingCancellationNotAllowedException::class);

        $this->service->cancelBooking(new CancelBookingDTO(bookingId: $bookingId, userId: $stranger->id));
    }

    public function test_can_cancel_needs_ownership_and_cancel_own_or_cancel_any(): void
    {
        $owner = $this->user;
        $stranger = UserModel::factory()->create();

        $this->assertFalse($this->service->canCancel($owner->id, $owner->id));

        $this->grant($owner, ['venuebookings:bookings.cancel_own']);
        $this->grant($stranger, ['venuebookings:bookings.cancel_own']);
        $this->assertTrue($this->service->canCancel($owner->id, $owner->id));
        $this->assertFalse($this->service->canCancel($stranger->id, $owner->id));

        $manager = $this->editorWithPermissions(['venuebookings:bookings.cancel_any']);
        $this->assertTrue($this->service->canCancel($manager->id, $owner->id));

        $this->assertFalse($this->service->canCancel((string) Str::uuid(), (string) Str::uuid()));
    }

    public function test_it_refuses_a_booking_whose_start_time_has_been_reached(): void
    {
        Event::fake();
        $this->travelTo(CarbonImmutable::create(2026, 3, 16, 10, 0, 0, self::VENUE_TIMEZONE));

        try {
            $this->service->createBooking($this->dtoFor('2026-03-16', '10:00', '12:00'));
            $this->fail('A booking that had already started was created.');
        } catch (BookingAlreadyStartedException) {
            $this->assertDatabaseCount('venuebookings_bookings', 0);
            Event::assertNotDispatched(BookingCreated::class);
        }
    }

    public function test_it_accepts_a_booking_until_the_second_before_it_starts(): void
    {
        Event::fake();
        // These tests are about the start cutoff, not the minimum advance
        config()->set('venue-bookings.min_advance_minutes', 0);
        $this->travelTo(CarbonImmutable::create(2026, 3, 16, 9, 59, 59, self::VENUE_TIMEZONE));

        $result = $this->service->createBooking($this->dtoFor('2026-03-16', '10:00', '12:00'));

        $this->assertDatabaseHas('venuebookings_bookings', ['id' => $result->id]);
    }

    public function test_it_refuses_a_booking_on_a_past_day(): void
    {
        Event::fake();
        $this->travelTo(CarbonImmutable::create(2026, 3, 17, 8, 0, 0, self::VENUE_TIMEZONE));

        $this->expectException(BookingAlreadyStartedException::class);

        $this->service->createBooking($this->dtoFor('2026-03-16', '18:00', '20:00'));
    }

    public function test_it_reads_the_start_in_the_venue_timezone(): void
    {
        Event::fake();
        // These tests are about the start cutoff, not the minimum advance
        config()->set('venue-bookings.min_advance_minutes', 0);
        // Canary time is UTC+0 in March, an hour behind Madrid: 09:30 there is 10:30 in Madrid,
        // so a 10:00 slot at a Canary venue has not started yet
        config()->set('venue-bookings.timezone', 'Atlantic/Canary');
        $this->travelTo(CarbonImmutable::create(2026, 3, 16, 9, 30, 0, 'Atlantic/Canary'));

        $result = $this->service->createBooking($this->dtoFor('2026-03-16', '10:00', '12:00'));

        $this->assertDatabaseHas('venuebookings_bookings', ['id' => $result->id]);
    }

    public function test_an_owner_cannot_cancel_a_booking_that_has_started(): void
    {
        Event::fake();
        $this->grant($this->user, ['venuebookings:bookings.cancel_own']);
        $bookingId = $this->createConfirmedBookingOf($this->user);
        $this->travelTo(CarbonImmutable::create(2026, 3, 16, 10, 0, 0, self::VENUE_TIMEZONE));

        try {
            $this->service->cancelBooking(new CancelBookingDTO(bookingId: $bookingId, userId: $this->user->id));
            $this->fail('A booking that had started was cancelled from the website.');
        } catch (BookingCancellationNotAllowedException $e) {
            $this->assertTrue($e->bookingHasStarted());
            $this->assertDatabaseHas('venuebookings_bookings', ['id' => $bookingId, 'status' => 'confirmed']);
            Event::assertNotDispatched(BookingCancelled::class);
        }
    }

    public function test_an_owner_can_cancel_until_the_second_before_the_start(): void
    {
        Event::fake();
        $this->grant($this->user, ['venuebookings:bookings.cancel_own']);
        $bookingId = $this->createConfirmedBookingOf($this->user);
        $this->travelTo(CarbonImmutable::create(2026, 3, 16, 9, 59, 59, self::VENUE_TIMEZONE));

        $result = $this->service->cancelBooking(new CancelBookingDTO(bookingId: $bookingId, userId: $this->user->id));

        $this->assertEquals(BookingStatus::Cancelled, $result->status);
    }

    public function test_cancel_any_does_not_lift_the_start_cutoff_on_the_website(): void
    {
        $bookingId = $this->createConfirmedBookingOf($this->user);
        $manager = $this->editorWithPermissions(['venuebookings:bookings.cancel_any']);
        $this->travelTo(CarbonImmutable::create(2026, 3, 16, 11, 0, 0, self::VENUE_TIMEZONE));

        try {
            $this->service->cancelBooking(new CancelBookingDTO(bookingId: $bookingId, userId: $manager->id));
            $this->fail('cancel_any cancelled a started booking from the website.');
        } catch (BookingCancellationNotAllowedException $e) {
            $this->assertTrue($e->bookingHasStarted());
        }
    }

    public function test_a_stranger_is_refused_for_permission_before_the_start_is_checked(): void
    {
        $bookingId = $this->createConfirmedBookingOf($this->user);
        $stranger = UserModel::factory()->create();
        $this->travelTo(CarbonImmutable::create(2026, 3, 16, 11, 0, 0, self::VENUE_TIMEZONE));

        try {
            $this->service->cancelBooking(new CancelBookingDTO(bookingId: $bookingId, userId: $stranger->id));
            $this->fail('A stranger cancelled another user\'s booking.');
        } catch (BookingCancellationNotAllowedException $e) {
            // "Already started" would tell a stranger that the booking exists
            $this->assertFalse($e->bookingHasStarted());
        }
    }

    public function test_staff_cancel_a_booking_that_has_started(): void
    {
        Event::fake();
        $bookingId = $this->createConfirmedBookingOf($this->user);
        $this->travelTo(CarbonImmutable::create(2026, 3, 16, 11, 0, 0, self::VENUE_TIMEZONE));

        $result = $this->service->cancelBookingAsStaff(
            new CancelBookingDTO(bookingId: $bookingId, userId: UserModel::factory()->create()->id),
        );

        $this->assertEquals(BookingStatus::Cancelled, $result->status);
        Event::assertDispatched(BookingCancelled::class);
    }

    public function test_it_refuses_a_slot_inside_the_minimum_advance(): void
    {
        Event::fake();
        config()->set('venue-bookings.min_advance_minutes', 60);
        $this->travelTo(CarbonImmutable::create(2026, 3, 16, 9, 30, 0, self::VENUE_TIMEZONE));

        try {
            $this->service->createBooking($this->dtoFor('2026-03-16', '10:00', '11:00'));
            $this->fail('A booking inside the minimum advance was created.');
        } catch (TooSoonToBookException) {
            $this->assertDatabaseCount('venuebookings_bookings', 0);
            Event::assertNotDispatched(BookingCreated::class);
        }
    }

    public function test_it_accepts_a_slot_starting_right_at_the_minimum_advance(): void
    {
        Event::fake();
        config()->set('venue-bookings.min_advance_minutes', 60);
        $this->travelTo(CarbonImmutable::create(2026, 3, 16, 9, 0, 0, self::VENUE_TIMEZONE));

        $result = $this->service->createBooking($this->dtoFor('2026-03-16', '10:00', '11:00'));

        $this->assertDatabaseHas('venuebookings_bookings', ['id' => $result->id]);
    }

    public function test_it_refuses_a_day_beyond_the_maximum_days_ahead(): void
    {
        Event::fake();
        // From 2026-03-01 (setUp), 15 days ahead end on 2026-03-16
        config()->set('venue-bookings.max_future_days', 15);

        $this->service->createBooking($this->dtoFor('2026-03-16', '10:00', '11:00'));

        $this->expectException(TooFarToBookException::class);

        $this->service->createBooking($this->dtoFor('2026-03-17', '10:00', '11:00'));
    }

    public function test_it_refuses_a_user_at_the_active_booking_limit(): void
    {
        Event::fake();
        config()->set('venue-bookings.max_active_bookings_per_user', 2);
        $this->createConfirmedBookingOf($this->user);
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-17',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'pending',
        ]);

        $this->expectException(BookingLimitExceededException::class);

        $this->service->createBooking($this->dtoFor('2026-03-18', '10:00', '11:00'));
    }

    public function test_the_rules_bind_administrators_too(): void
    {
        Event::fake();
        config()->set('venue-bookings.max_active_bookings_per_user', 1);
        $admin = UserModel::factory()->admin()->create();
        $this->createConfirmedBookingOf($admin);

        $this->expectException(BookingLimitExceededException::class);

        $this->service->createBooking(new CreateBookingDTO(
            resourceId: $this->resource->id,
            userId: $admin->id,
            date: '2026-03-18',
            startTime: '10:00',
            endTime: '11:00',
        ));
    }

    public function test_it_refuses_a_range_off_the_slot_boundaries(): void
    {
        Event::fake();

        try {
            $this->service->createBooking($this->dtoFor('2026-03-16', '10:30', '11:30'));
            $this->fail('A range off the slot boundaries was booked.');
        } catch (SlotOutsideScheduleException) {
            $this->assertDatabaseCount('venuebookings_bookings', 0);
            Event::assertNotDispatched(BookingCreated::class);
        }
    }

    public function test_it_refuses_a_range_past_closing_time(): void
    {
        Event::fake();

        $this->expectException(SlotOutsideScheduleException::class);

        $this->service->createBooking($this->dtoFor('2026-03-16', '21:00', '23:00'));
    }

    public function test_it_refuses_a_closed_day(): void
    {
        Event::fake();
        $this->useSchedule([['day_of_week' => 1, 'open_time' => '10:00', 'close_time' => '20:00', 'is_enabled' => true]]);

        // 2026-03-17 is a Tuesday
        $this->expectException(SlotOutsideScheduleException::class);

        $this->service->createBooking($this->dtoFor('2026-03-17', '10:00', '11:00'));
    }

    public function test_it_refuses_a_resource_without_schedule(): void
    {
        Event::fake();
        OperatingScheduleModel::query()->where('resource_id', $this->resource->id)->delete();

        $this->expectException(SlotOutsideScheduleException::class);

        $this->service->createBooking($this->dtoFor('2026-03-16', '10:00', '11:00'));
    }

    public function test_it_refuses_a_resource_that_is_not_active(): void
    {
        Event::fake();

        // The booking page only offers active resources
        foreach (['inactive', 'maintenance'] as $status) {
            $this->resource->update(['status' => $status]);

            try {
                $this->service->createBooking($this->dtoFor('2026-03-16', '10:00', '11:00'));
                $this->fail("A {$status} resource was booked.");
            } catch (BookableResourceUnavailableException) {
                $this->assertDatabaseCount('venuebookings_bookings', 0);
            }
        }

        Event::assertNotDispatched(BookingCreated::class);
    }

    public function test_a_busy_lock_refuses_the_booking_without_saving_it(): void
    {
        Event::fake();
        $service = $this->makeService($this->lockThatFirstRuns(function (): never {
            throw BookingBusyException::create();
        }));

        try {
            $service->createBooking($this->dtoFor('2026-03-16', '10:00', '11:00'));
            $this->fail('A booking was created without its lock.');
        } catch (BookingBusyException) {
            $this->assertDatabaseCount('venuebookings_bookings', 0);
            Event::assertNotDispatched(BookingCreated::class);
        }
    }

    public function test_the_lock_covers_the_user_the_resource_and_the_day(): void
    {
        Event::fake();
        $locked = [];
        $lock = $this->lockThatFirstRuns(function (string $userId, string $resourceId, string $date) use (&$locked): void {
            $locked[] = [$userId, $resourceId, $date];
        });

        $this->makeService($lock)->createBooking($this->dtoFor('2026-03-16', '10:00', '11:00'));

        $this->assertSame([[$this->user->id, $this->resource->id, '2026-03-16']], $locked);
    }

    public function test_the_overlap_is_checked_while_holding_the_lock(): void
    {
        Event::fake();
        // Another request holding the lock books the same slot first
        $service = $this->makeService($this->lockThatFirstRuns(function (): void {
            BookingModel::create([
                'id' => (string) Str::uuid(),
                'resource_id' => $this->resource->id,
                'user_id' => UserModel::factory()->create()->id,
                'date' => '2026-03-16',
                'start_time' => '10:00',
                'end_time' => '11:00',
                'status' => 'confirmed',
            ]);
        }));

        try {
            $service->createBooking($this->dtoFor('2026-03-16', '10:00', '11:00'));
            $this->fail('Two bookings were made for the same slot.');
        } catch (SlotUnavailableException) {
            $this->assertDatabaseCount('venuebookings_bookings', 1);
        }
    }

    public function test_the_active_booking_limit_is_checked_while_holding_the_lock(): void
    {
        Event::fake();
        config()->set('venue-bookings.max_active_bookings_per_user', 1);
        // Another request of the same user, holding the lock, takes their last booking
        $service = $this->makeService($this->lockThatFirstRuns(function (): void {
            $this->createConfirmedBookingOf($this->user);
        }));

        try {
            $service->createBooking($this->dtoFor('2026-03-18', '10:00', '11:00'));
            $this->fail('The user went over the active booking limit.');
        } catch (BookingLimitExceededException) {
            $this->assertDatabaseCount('venuebookings_bookings', 1);
        }
    }

    public function test_it_accepts_a_run_of_thirty_minute_slots(): void
    {
        Event::fake();
        $this->useSchedule(
            [['day_of_week' => 1, 'open_time' => '10:00', 'close_time' => '14:00', 'is_enabled' => true]],
            slotMinutes: 30,
        );

        $result = $this->service->createBooking($this->dtoFor('2026-03-16', '10:30', '11:30'));

        $this->assertDatabaseHas('venuebookings_bookings', ['id' => $result->id]);
    }

    public function test_a_whole_block_is_booked_whatever_the_minimum_consecutive_slots(): void
    {
        Event::fake();
        // The booking page never applies the minimum to blocks
        $this->useSchedule([
            ['day_of_week' => 1, 'open_time' => '10:00', 'close_time' => '14:00', 'is_enabled' => true, 'label' => 'Mañana'],
            ['day_of_week' => 1, 'open_time' => '16:00', 'close_time' => '20:00', 'is_enabled' => true, 'label' => 'Tarde'],
        ], mode: 'time_blocks', minConsecutive: 5);

        $result = $this->service->createBooking($this->dtoFor('2026-03-16', '16:00', '20:00'));

        $this->assertDatabaseHas('venuebookings_bookings', ['id' => $result->id]);
    }

    public function test_it_refuses_a_range_spanning_two_blocks(): void
    {
        Event::fake();
        $this->useSchedule([
            ['day_of_week' => 1, 'open_time' => '10:00', 'close_time' => '14:00', 'is_enabled' => true, 'label' => 'Mañana'],
            ['day_of_week' => 1, 'open_time' => '16:00', 'close_time' => '20:00', 'is_enabled' => true, 'label' => 'Tarde'],
        ], mode: 'time_blocks');

        $this->expectException(SlotOutsideScheduleException::class);

        $this->service->createBooking($this->dtoFor('2026-03-16', '10:00', '20:00'));
    }

    public function test_it_refuses_more_than_the_maximum_consecutive_slots(): void
    {
        Event::fake();
        // openEveryDay() allows at most 6 consecutive slots; 08:00-15:00 is 7
        try {
            $this->service->createBooking($this->dtoFor('2026-03-16', '08:00', '15:00'));
            $this->fail('A run longer than the maximum consecutive slots was booked.');
        } catch (MaximumSlotsExceededException) {
            $this->assertDatabaseCount('venuebookings_bookings', 0);
            Event::assertNotDispatched(BookingCreated::class);
        }
    }

    public function test_it_accepts_exactly_the_maximum_consecutive_slots(): void
    {
        Event::fake();

        $result = $this->service->createBooking($this->dtoFor('2026-03-16', '08:00', '14:00'));

        $this->assertDatabaseHas('venuebookings_bookings', ['id' => $result->id]);
    }

    public function test_a_maximum_below_one_still_lets_one_slot_through(): void
    {
        Event::fake();
        OperatingScheduleModel::query()->where('resource_id', $this->resource->id)->firstOrFail()
            ->update(['max_consecutive_slots' => 0]);

        $result = $this->service->createBooking($this->dtoFor('2026-03-16', '10:00', '11:00'));

        $this->assertDatabaseHas('venuebookings_bookings', ['id' => $result->id]);
    }

    public function test_it_accepts_a_run_that_crosses_midnight(): void
    {
        Event::fake();
        // 2026-03-20 is a Friday
        $this->useSchedule([['day_of_week' => 5, 'open_time' => '22:00', 'close_time' => '02:00', 'is_enabled' => true]]);

        $result = $this->service->createBooking($this->dtoFor('2026-03-20', '23:00', '01:00'));

        $this->assertDatabaseHas('venuebookings_bookings', ['id' => $result->id]);
    }

    /**
     * @param  array<string>  $permissionKeys
     */
    private function grant(UserModel $user, array $permissionKeys): void
    {
        $role = RoleModel::create(['name' => 'grant_'.uniqid(), 'display_name' => 'Grant']);

        foreach ($permissionKeys as $key) {
            [$resource, $action] = explode('.', $key, 2);
            $permission = PermissionModel::firstOrCreate(
                ['key' => $key],
                ['label' => $key, 'resource' => $resource, 'action' => $action],
            );
            $role->permissions()->attach($permission->id);
        }

        $user->roles()->attach($role->id);
        app(AuthorizationServiceInterface::class)->invalidateCache();
    }

    private function makeService(BookingLockInterface $lock): BookingService
    {
        $rules = new BookingRules($this->bookingRepository, new BookingSettingsReader);

        return new BookingService(
            $this->bookingRepository,
            new SlotAvailabilityService(new EloquentOperatingScheduleRepository, $this->bookingRepository, $rules),
            new BookingSettingsReader,
            new EloquentOperatingScheduleRepository,
            app(AuthorizationServiceInterface::class),
            $rules,
            new EloquentBookableResourceRepository,
            $lock,
        );
    }

    /**
     * A lock that, while "holding" it, first runs $before (as another request would, given the
     * user, resource and day it locks) and then the booking.
     */
    private function lockThatFirstRuns(Closure $before): BookingLockInterface
    {
        return new class($before) implements BookingLockInterface
        {
            public function __construct(private readonly Closure $before) {}

            public function forBooking(string $userId, string $resourceId, string $date, Closure $callback): mixed
            {
                ($this->before)($userId, $resourceId, $date);

                return $callback();
            }
        };
    }

    private function dtoFor(string $date, string $startTime, string $endTime): CreateBookingDTO
    {
        return new CreateBookingDTO(
            resourceId: $this->resource->id,
            userId: $this->user->id,
            date: $date,
            startTime: $startTime,
            endTime: $endTime,
        );
    }

    private function createConfirmedBookingOf(UserModel $user): string
    {
        $bookingId = (string) Str::uuid();
        BookingModel::create([
            'id' => $bookingId,
            'resource_id' => $this->resource->id,
            'user_id' => $user->id,
            'date' => '2026-03-16',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'confirmed',
        ]);

        return $bookingId;
    }

    /**
     * Replaces the room's schedule (a resource has a single schedule).
     *
     * @param  array<array<string, mixed>>  $daySchedules
     */
    private function useSchedule(
        array $daySchedules,
        int $slotMinutes = 60,
        string $mode = 'time_slots',
        int $minConsecutive = 1,
    ): void {
        OperatingScheduleModel::query()
            ->where('resource_id', $this->resource->id)
            ->firstOrFail()
            ->update([
                'slot_duration_minutes' => $slotMinutes,
                'scheduling_mode' => $mode,
                'min_consecutive_slots' => $minConsecutive,
                'day_schedules' => $daySchedules,
            ]);
    }
}
