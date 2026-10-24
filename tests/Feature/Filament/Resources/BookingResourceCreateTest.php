<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Filament\Resources;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\VenueBookings\Application\Services\BookingLockInterface;
use Modules\VenueBookings\Domain\Events\BookingConfirmed;
use Modules\VenueBookings\Domain\Events\BookingCreated;
use Modules\VenueBookings\Domain\Exceptions\BookingBusyException;
use Modules\VenueBookings\Filament\Resources\BookingResource;
use Modules\VenueBookings\Filament\Resources\BookingResource\Pages\CreateBooking;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\OperatingScheduleModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Bookings created in the panel go through BookingService: the same overlap and schedule checks
 * as the website under the same lock, without the settings rules (the staff make the exception).
 */
final class BookingResourceCreateTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    private const string VENUE_TIMEZONE = 'Europe/Madrid';

    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    private BookableResourceModel $bookableResource;

    private UserModel $admin;

    private UserModel $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([BookingResource::class]);

        config()->set('venue-bookings.timezone', self::VENUE_TIMEZONE);
        $this->travelTo(CarbonImmutable::create(2026, 3, 1, 9, 0, 0, self::VENUE_TIMEZONE));

        $this->bookableResource = BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Room',
            'slug' => 'test-room',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        // Open every day 08:00-22:00 in hourly slots
        OperatingScheduleModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->bookableResource->id,
            'slot_duration_minutes' => 60,
            'min_consecutive_slots' => 1,
            'max_consecutive_slots' => 6,
            'day_schedules' => array_map(
                fn (int $day): array => ['day_of_week' => $day, 'open_time' => '08:00', 'close_time' => '22:00', 'is_enabled' => true],
                range(0, 6),
            ),
        ]);

        $this->admin = UserModel::factory()->admin()->create();
        $this->member = UserModel::factory()->create();

        config()->set('modules.settings.venue-bookings.approval_mode', 'require_approval');
        config()->set('modules.settings.venue-bookings.max_active_bookings_per_user', 3);
        config()->set('modules.settings.venue-bookings.min_advance_minutes', 60);
        config()->set('modules.settings.venue-bookings.max_future_days', 30);
        config()->set('modules.settings.venue-bookings.predefined_fields', [
            'activity_name' => ['enabled' => false, 'required' => false, 'visibility' => 'public'],
            'num_participants' => ['enabled' => false, 'required' => false, 'visibility' => 'public'],
            'contact_phone' => ['enabled' => false, 'required' => false, 'visibility' => 'public'],
            'notes' => ['enabled' => false, 'required' => false, 'visibility' => 'public'],
        ]);
        config()->set('modules.settings.venue-bookings.custom_fields', []);
    }

    public function test_it_creates_the_booking_through_the_booking_service(): void
    {
        Event::fake([BookingCreated::class, BookingConfirmed::class]);
        $this->actingAs($this->admin);

        Livewire::test(CreateBooking::class)
            ->fillForm($this->formFor('2026-03-16', '10:00', '12:00') + ['admin_notes' => 'Llaves en recepción'])
            ->call('create')
            ->assertHasNoFormErrors();

        $booking = BookingModel::query()->sole();
        $this->assertSame($this->member->id, $booking->user_id);
        $this->assertSame('2026-03-16', $booking->date->format('Y-m-d'));
        $this->assertSame('pending', $booking->status->value);
        $this->assertSame('Llaves en recepción', $booking->admin_notes);
        Event::assertDispatched(BookingCreated::class);
        Event::assertNotDispatched(BookingConfirmed::class);
    }

    public function test_it_follows_the_approval_mode(): void
    {
        Event::fake([BookingCreated::class, BookingConfirmed::class]);
        config()->set('modules.settings.venue-bookings.approval_mode', 'auto_confirm');
        $this->actingAs($this->admin);

        Livewire::test(CreateBooking::class)
            ->fillForm($this->formFor('2026-03-16', '10:00', '11:00'))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('confirmed', BookingModel::query()->sole()->status->value);
        Event::assertDispatched(BookingConfirmed::class);
    }

    public function test_it_refuses_a_booking_that_overlaps_another(): void
    {
        $this->actingAs($this->admin);
        $this->createBookingOf($this->member, '2026-03-16', '10:00', '12:00');

        Livewire::test(CreateBooking::class)
            ->fillForm($this->formFor('2026-03-16', '11:00', '13:00'))
            ->call('create')
            ->assertHasFormErrors(['start_time']);

        $this->assertSame(1, BookingModel::query()->count());
    }

    public function test_it_refuses_a_range_outside_the_schedule(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateBooking::class)
            ->fillForm($this->formFor('2026-03-16', '22:00', '23:00'))
            ->call('create')
            ->assertHasFormErrors(['start_time']);

        $this->assertSame(0, BookingModel::query()->count());
    }

    public function test_it_refuses_a_range_with_the_same_start_and_end(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateBooking::class)
            ->fillForm($this->formFor('2026-03-16', '10:00', '10:00'))
            ->call('create')
            ->assertHasFormErrors(['start_time']);

        $this->assertSame(0, BookingModel::query()->count());
    }

    public function test_the_staff_may_exceed_the_active_booking_limit(): void
    {
        Event::fake([BookingCreated::class, BookingConfirmed::class]);
        config()->set('modules.settings.venue-bookings.max_active_bookings_per_user', 1);
        $this->actingAs($this->admin);
        $this->createBookingOf($this->member, '2026-03-17', '10:00', '11:00');

        Livewire::test(CreateBooking::class)
            ->fillForm($this->formFor('2026-03-16', '10:00', '11:00'))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2, BookingModel::query()->count());
    }

    public function test_the_staff_may_book_beyond_the_days_ahead(): void
    {
        Event::fake([BookingCreated::class, BookingConfirmed::class]);
        $this->actingAs($this->admin);

        Livewire::test(CreateBooking::class)
            ->fillForm($this->formFor('2026-04-15', '10:00', '11:00'))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, BookingModel::query()->count());
    }

    public function test_the_staff_may_book_within_the_minimum_advance(): void
    {
        // 09:00 now: 10:00 starts within the two hours of advance
        Event::fake([BookingCreated::class, BookingConfirmed::class]);
        config()->set('modules.settings.venue-bookings.min_advance_minutes', 120);
        $this->actingAs($this->admin);

        Livewire::test(CreateBooking::class)
            ->fillForm($this->formFor('2026-03-01', '10:00', '11:00'))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, BookingModel::query()->count());
    }

    public function test_a_slot_that_has_started_cannot_be_booked(): void
    {
        // 09:00 now
        $this->actingAs($this->admin);

        Livewire::test(CreateBooking::class)
            ->fillForm($this->formFor('2026-03-01', '08:00', '10:00'))
            ->call('create')
            ->assertHasFormErrors(['start_time']);

        $this->assertSame(0, BookingModel::query()->count());
    }

    public function test_a_busy_lock_asks_to_try_again(): void
    {
        $this->app->instance(BookingLockInterface::class, new class implements BookingLockInterface
        {
            public function forBooking(string $userId, string $resourceId, Closure $callback): mixed
            {
                throw BookingBusyException::create();
            }
        });
        $this->actingAs($this->admin);

        Livewire::test(CreateBooking::class)
            ->fillForm($this->formFor('2026-03-16', '10:00', '11:00'))
            ->call('create')
            ->assertNotified('Ahora mismo se está procesando otra reserva. Inténtalo de nuevo en unos segundos.');

        $this->assertSame(0, BookingModel::query()->count());
    }

    /**
     * @return array<string, string>
     */
    private function formFor(string $date, string $startTime, string $endTime): array
    {
        return [
            'resource_id' => $this->bookableResource->id,
            'user_id' => $this->member->id,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ];
    }

    private function createBookingOf(UserModel $user, string $date, string $startTime, string $endTime): void
    {
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->bookableResource->id,
            'user_id' => $user->id,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => 'confirmed',
        ]);
    }
}
