<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Integration\Infrastructure\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
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
use Modules\VenueBookings\Domain\Exceptions\BookingAlreadyStartedException;
use Modules\VenueBookings\Domain\Exceptions\BookingCancellationNotAllowedException;
use Modules\VenueBookings\Domain\Exceptions\MinimumSlotsNotMetException;
use Modules\VenueBookings\Domain\Exceptions\SlotUnavailableException;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\OperatingScheduleModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Repositories\EloquentBookingRepository;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Repositories\EloquentOperatingScheduleRepository;
use Modules\VenueBookings\Infrastructure\Services\BookingRules;
use Modules\VenueBookings\Infrastructure\Services\BookingService;
use Modules\VenueBookings\Infrastructure\Services\BookingSettingsReader;
use Modules\VenueBookings\Infrastructure\Services\SlotAvailabilityService;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\TestCase;

final class BookingServiceTest extends TestCase
{
    use CreatesUsersWithPermissions;
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
        $slotAvailabilityService = new SlotAvailabilityService(
            new EloquentOperatingScheduleRepository,
            $this->bookingRepository,
            new BookingRules($this->bookingRepository, new BookingSettingsReader),
        );

        $scheduleRepository = new EloquentOperatingScheduleRepository;

        $this->service = new BookingService(
            $this->bookingRepository,
            $slotAvailabilityService,
            new BookingSettingsReader,
            $scheduleRepository,
            app(AuthorizationServiceInterface::class),
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
}
