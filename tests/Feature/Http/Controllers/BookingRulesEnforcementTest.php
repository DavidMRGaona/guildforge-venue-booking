<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\VenueBookings\Application\Services\BookingLockInterface;
use Modules\VenueBookings\Domain\Exceptions\BookingBusyException;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\OperatingScheduleModel;
use Modules\VenueBookings\Tests\Support\OpensResourceEveryDay;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The settings rules and the resource schedule are enforced when the booking is sent,
 * for every account, and a range built from the slots the page lists is always accepted.
 */
final class BookingRulesEnforcementTest extends ModuleTestCase
{
    use OpensResourceEveryDay;

    private const string VENUE_TIMEZONE = 'Europe/Madrid';

    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    private BookableResourceModel $room;

    private UserModel $user;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        // Make the module routes, added after boot, resolvable by name
        app('router')->getRoutes()->refreshNameLookups();

        // Tuesday 2026-11-10, 12:00 at the venue
        config()->set('modules.settings.venue-bookings.timezone', self::VENUE_TIMEZONE);
        $this->travelTo(CarbonImmutable::create(2026, 11, 10, 12, 0, 0, self::VENUE_TIMEZONE));

        config()->set('modules.settings.venue-bookings.max_active_bookings_per_user', 2);
        config()->set('modules.settings.venue-bookings.min_advance_minutes', 120);
        config()->set('modules.settings.venue-bookings.max_future_days', 14);

        $this->room = BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sala grande',
            'slug' => 'sala-grande',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $this->openEveryDay($this->room->id, '10:00', '22:00');

        $this->user = UserModel::factory()->create();
    }

    public function test_a_slot_inside_the_minimum_advance_is_refused(): void
    {
        $this->book($this->user, '2026-11-10', '13:00', '14:00')
            ->assertRedirect(route('bookings.index'))
            ->assertSessionHas('error', 'Las reservas deben hacerse con más antelación.');

        $this->assertSame(0, BookingModel::query()->count());
    }

    public function test_a_slot_right_at_the_minimum_advance_is_booked(): void
    {
        $this->book($this->user, '2026-11-10', '14:00', '15:00')
            ->assertRedirect(route('bookings.my-bookings'));

        $this->assertSame(1, BookingModel::query()->count());
    }

    public function test_a_day_beyond_the_maximum_days_ahead_is_refused(): void
    {
        $this->book($this->user, '2026-11-25', '10:00', '11:00')
            ->assertRedirect(route('bookings.index'))
            ->assertSessionHas('error', 'No se pueden hacer reservas con tanta antelación.');

        $this->book($this->user, '2026-11-24', '10:00', '11:00')
            ->assertRedirect(route('bookings.my-bookings'));

        $this->assertSame(1, BookingModel::query()->count());
    }

    public function test_a_user_at_the_active_booking_limit_is_refused(): void
    {
        $this->bookingOf($this->user, 'pending');
        $this->bookingOf($this->user, 'confirmed');

        $this->book($this->user, '2026-11-12', '10:00', '11:00')
            ->assertRedirect(route('bookings.index'))
            ->assertSessionHas('error', 'Has alcanzado el máximo de reservas activas.');

        $this->assertSame(2, BookingModel::query()->count());
    }

    public function test_the_active_booking_limit_binds_administrators_too(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->bookingOf($admin, 'pending');
        $this->bookingOf($admin, 'confirmed');

        $this->book($admin, '2026-11-12', '10:00', '11:00')
            ->assertSessionHas('error', 'Has alcanzado el máximo de reservas activas.');

        $this->assertSame(2, BookingModel::query()->count());
    }

    public function test_the_minimum_advance_binds_administrators_too(): void
    {
        // No other bookings, so only the minimum advance can refuse it
        $admin = UserModel::factory()->admin()->create();

        $this->book($admin, '2026-11-10', '13:00', '14:00')
            ->assertSessionHas('error', 'Las reservas deben hacerse con más antelación.');

        $this->assertSame(0, BookingModel::query()->count());
    }

    public function test_cancelled_bookings_do_not_count_toward_the_limit(): void
    {
        $this->bookingOf($this->user, 'cancelled');
        $this->bookingOf($this->user, 'cancelled');

        $this->book($this->user, '2026-11-12', '10:00', '11:00')
            ->assertRedirect(route('bookings.my-bookings'));
    }

    public function test_a_range_off_the_slot_boundaries_is_refused_on_the_start_time_field(): void
    {
        $this->book($this->user, '2026-11-12', '18:30', '19:30')
            ->assertRedirect(route('bookings.index'))
            ->assertSessionHasErrors([
                'start_time' => 'La franja elegida no coincide con los turnos del espacio. Elige uno de los turnos disponibles.',
            ]);

        $this->assertSame(0, BookingModel::query()->count());
    }

    public function test_a_range_past_closing_time_is_refused(): void
    {
        $this->book($this->user, '2026-11-12', '21:00', '23:00')
            ->assertSessionHasErrors(['start_time']);

        $this->assertSame(0, BookingModel::query()->count());
    }

    public function test_more_than_the_maximum_consecutive_slots_is_refused(): void
    {
        // The room allows at most 4 consecutive slots; 10:00-15:00 is 5
        $this->book($this->user, '2026-11-12', '10:00', '15:00')
            ->assertRedirect(route('bookings.index'))
            ->assertSessionHas('error', 'Has elegido más turnos consecutivos de los permitidos para este espacio.');

        $this->assertSame(0, BookingModel::query()->count());
    }

    public function test_a_closed_day_is_refused(): void
    {
        // Only Mondays; 2026-11-12 is a Thursday
        OperatingScheduleModel::query()->where('resource_id', $this->room->id)->firstOrFail()->update([
            'day_schedules' => [['day_of_week' => 1, 'open_time' => '10:00', 'close_time' => '22:00', 'is_enabled' => true]],
        ]);

        $this->book($this->user, '2026-11-12', '10:00', '11:00')
            ->assertSessionHasErrors(['start_time']);

        $this->assertSame(0, BookingModel::query()->count());
    }

    public function test_an_inactive_resource_is_refused(): void
    {
        $this->room->update(['status' => 'inactive']);

        $this->book($this->user, '2026-11-12', '10:00', '11:00')
            ->assertRedirect(route('bookings.index'))
            ->assertSessionHas('error', 'Este espacio no admite reservas en este momento.');

        $this->assertSame(0, BookingModel::query()->count());
    }

    public function test_a_booking_that_cannot_get_its_lock_asks_to_try_again(): void
    {
        $this->app->instance(BookingLockInterface::class, new class implements BookingLockInterface
        {
            public function forBooking(string $userId, string $resourceId, Closure $callback): mixed
            {
                throw BookingBusyException::create();
            }
        });

        $this->book($this->user, '2026-11-12', '10:00', '11:00')
            ->assertRedirect(route('bookings.index'))
            ->assertSessionHas('error', 'Ahora mismo se está procesando otra reserva. Inténtalo de nuevo en unos segundos.');

        $this->assertSame(0, BookingModel::query()->count());
    }

    public function test_a_run_of_slots_listed_by_the_page_is_booked(): void
    {
        $slots = $this->listedSlots('2026-11-12');

        // Three consecutive slots, as the picker builds them: first start, last end
        $this->book($this->user, '2026-11-12', $slots[2]['start_time'], $slots[4]['end_time'])
            ->assertRedirect(route('bookings.my-bookings'));

        $this->assertDatabaseHas('venuebookings_bookings', ['date' => '2026-11-12']);
    }

    public function test_a_block_listed_by_the_page_is_booked(): void
    {
        OperatingScheduleModel::query()->where('resource_id', $this->room->id)->firstOrFail()->update([
            'scheduling_mode' => 'time_blocks',
            'min_consecutive_slots' => 3,
            'day_schedules' => [
                ['day_of_week' => 4, 'open_time' => '10:00', 'close_time' => '14:00', 'is_enabled' => true, 'label' => 'Mañana'],
                ['day_of_week' => 4, 'open_time' => '16:00', 'close_time' => '20:00', 'is_enabled' => true, 'label' => 'Tarde'],
            ],
        ]);

        $slots = $this->listedSlots('2026-11-12');

        $this->book($this->user, '2026-11-12', $slots[1]['start_time'], $slots[1]['end_time'])
            ->assertRedirect(route('bookings.my-bookings'));

        $this->assertSame(1, BookingModel::query()->count());
    }

    public function test_a_block_closing_after_midnight_stored_without_leading_zero_is_booked(): void
    {
        OperatingScheduleModel::query()->where('resource_id', $this->room->id)->firstOrFail()->update([
            'scheduling_mode' => 'time_blocks',
            'min_consecutive_slots' => 1,
            'day_schedules' => [
                ['day_of_week' => 4, 'open_time' => '18:00', 'close_time' => '3:00', 'is_enabled' => true, 'label' => 'Noche'],
            ],
        ]);

        $slots = $this->listedSlots('2026-11-12');

        $this->assertSame('03:00', $slots[0]['end_time']);

        $this->book($this->user, '2026-11-12', $slots[0]['start_time'], $slots[0]['end_time'])
            ->assertRedirect(route('bookings.my-bookings'));

        $this->assertSame(1, BookingModel::query()->count());
    }

    private function book(UserModel $user, string $date, string $startTime, string $endTime): TestResponse
    {
        return $this->actingAs($user)
            ->from(route('bookings.index'))
            ->post(route('bookings.store'), [
                'resource_id' => $this->room->id,
                'date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
            ]);
    }

    /**
     * @return array<int, array{start_time: string, end_time: string, is_available: bool, label: ?string}>
     */
    private function listedSlots(string $date): array
    {
        /** @var array<int, array{start_time: string, end_time: string, is_available: bool, label: ?string}> $slots */
        $slots = $this->getJson(route('bookings.api.slots', ['resource_id' => $this->room->id, 'date' => $date]))
            ->assertOk()
            ->json();

        return $slots;
    }

    private function bookingOf(UserModel $user, string $status): void
    {
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->room->id,
            'user_id' => $user->id,
            'date' => '2026-11-20',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => $status,
        ]);
    }
}
