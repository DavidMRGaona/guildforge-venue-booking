<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Http\Controllers;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Modules\VenueBookings\Infrastructure\Services\ProfileBookingsDataProvider;
use Tests\Support\Modules\ModuleTestCase;

/**
 * A booking closes at its start time, read in the venue timezone: from then on the website
 * neither creates nor cancels it, and the pages stop offering to. The panel keeps no cutoff.
 */
final class BookingStartCutoffTest extends ModuleTestCase
{
    private const string VENUE_TIMEZONE = 'Europe/Madrid';

    private const string CANCEL_OWN = 'venuebookings:bookings.cancel_own';

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

        // Tuesday 2026-11-10, 18:30 at the venue
        config()->set('venue-bookings.timezone', self::VENUE_TIMEZONE);
        $this->travelTo(CarbonImmutable::create(2026, 11, 10, 18, 30, 0, self::VENUE_TIMEZONE));

        $this->room = BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sala grande',
            'slug' => 'sala-grande',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $this->user = UserModel::factory()->create();

        // The first test of a process starts with the module's translation group cached as empty
        // (the lazy database refresh rebuilds the application), so the literal Spanish texts asserted
        // below would not resolve when this class runs alone. Dropping the cache is harmless otherwise.
        app('translator')->setLoaded([]);
    }

    public function test_a_booking_on_a_past_day_is_refused_on_the_date_field(): void
    {
        $this->actingAs($this->user)
            ->from(route('bookings.index'))
            ->post(route('bookings.store'), $this->payload('2026-11-09', '18:00', '20:00'))
            ->assertRedirect(route('bookings.index'))
            ->assertSessionHasErrors(['date' => 'No se pueden hacer reservas para días pasados.']);

        $this->assertSame(0, BookingModel::query()->count());
    }

    public function test_a_slot_that_has_already_started_today_is_refused_on_the_start_time_field(): void
    {
        // The picker of a page left open still lists 18:00; it began half an hour ago
        $this->actingAs($this->user)
            ->from(route('bookings.index'))
            ->post(route('bookings.store'), $this->payload('2026-11-10', '18:00', '20:00'))
            ->assertRedirect(route('bookings.index'))
            ->assertSessionHasErrors(['start_time' => 'Esa franja ya ha empezado. Elige una hora posterior.']);

        $this->assertSame(0, BookingModel::query()->count());
    }

    public function test_a_later_slot_today_is_still_booked(): void
    {
        $this->actingAs($this->user)
            ->from(route('bookings.index'))
            ->post(route('bookings.store'), $this->payload('2026-11-10', '19:00', '20:00'))
            ->assertRedirect(route('bookings.my-bookings'))
            ->assertSessionHas('success');

        $this->assertSame(1, BookingModel::query()->count());
    }

    public function test_the_past_day_check_uses_the_venue_day(): void
    {
        // At 01:00 on the 11th in Auckland (12:00 UTC on the 10th),
        // where the 10th is already over
        config()->set('venue-bookings.timezone', 'Pacific/Auckland');
        $this->travelTo(CarbonImmutable::create(2026, 11, 11, 1, 0, 0, 'Pacific/Auckland'));

        $this->actingAs($this->user)
            ->from(route('bookings.index'))
            ->post(route('bookings.store'), $this->payload('2026-11-10', '23:00', '23:30'))
            ->assertSessionHasErrors(['date' => 'No se pueden hacer reservas para días pasados.']);

        $this->actingAs($this->user)
            ->from(route('bookings.index'))
            ->post(route('bookings.store'), $this->payload('2026-11-11', '10:00', '12:00'))
            ->assertRedirect(route('bookings.my-bookings'));

        $this->assertSame(1, BookingModel::query()->count());
    }

    public function test_the_owner_cannot_cancel_a_booking_that_has_started(): void
    {
        $this->grant($this->user, [self::CANCEL_OWN]);
        $booking = $this->bookingOf($this->user, '18:00', '20:00');

        $this->actingAs($this->user)
            ->from(route('bookings.my-bookings'))
            ->delete(route('bookings.cancel', $booking->id))
            ->assertRedirect(route('bookings.my-bookings'))
            ->assertSessionHas('error', 'La reserva ya ha empezado y no se puede cancelar.');

        $this->assertDatabaseHas('venuebookings_bookings', ['id' => $booking->id, 'status' => 'confirmed']);
    }

    public function test_the_owner_still_cancels_a_booking_later_today(): void
    {
        $this->grant($this->user, [self::CANCEL_OWN]);
        $booking = $this->bookingOf($this->user, '19:00', '20:00');

        $this->actingAs($this->user)
            ->from(route('bookings.my-bookings'))
            ->delete(route('bookings.cancel', $booking->id))
            ->assertRedirect(route('bookings.my-bookings'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('venuebookings_bookings', ['id' => $booking->id, 'status' => 'cancelled']);
    }

    public function test_a_stranger_still_gets_the_plain_403_for_a_started_booking(): void
    {
        $booking = $this->bookingOf($this->user, '18:00', '20:00');
        $stranger = UserModel::factory()->create();
        $this->grant($stranger, [self::CANCEL_OWN]);

        $this->actingAs($stranger)
            ->delete(route('bookings.cancel', $booking->id))
            ->assertForbidden()
            ->assertSee('No puedes cancelar esta reserva.');

        $this->assertDatabaseHas('venuebookings_bookings', ['id' => $booking->id, 'status' => 'confirmed']);
    }

    public function test_a_malformed_date_is_a_validation_error_not_a_server_error(): void
    {
        foreach (['not-a-date', '2026-13-45', '', '2026-11-10 10:00'] as $garbage) {
            $this->actingAs($this->user)
                ->from(route('bookings.index'))
                ->post(route('bookings.store'), $this->payload($garbage, '19:00', '20:00'))
                ->assertRedirect(route('bookings.index'))
                ->assertSessionHasErrors(['date']);
        }

        $this->assertSame(0, BookingModel::query()->count());
    }

    public function test_my_bookings_stop_offering_to_cancel_once_a_booking_has_started(): void
    {
        $this->grant($this->user, [self::CANCEL_OWN]);
        $started = $this->bookingOf($this->user, '18:00', '19:00');
        $later = $this->bookingOf($this->user, '19:00', '20:00');

        // Same day, ordered by start time
        $this->actingAs($this->user)
            ->get(route('bookings.my-bookings'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('VenueBookings/MyBookings', false)
                ->where('canCancel', true)
                ->where('bookings.0.id', $started->id)
                ->where('bookings.0.is_cancellable', false)
                ->where('bookings.1.id', $later->id)
                ->where('bookings.1.is_cancellable', true));
    }

    public function test_the_profile_stops_offering_to_cancel_once_a_booking_has_started(): void
    {
        $started = $this->bookingOf($this->user, '18:00', '19:00');
        $later = $this->bookingOf($this->user, '19:00', '20:00');

        $data = $this->app->make(ProfileBookingsDataProvider::class)->getDataForUser($this->user->id);
        $this->assertNotNull($data);

        $flags = [];
        foreach ($data['upcoming'] as $booking) {
            $flags[$booking['id']] = $booking['is_cancellable'];
        }

        $this->assertFalse($flags[$started->id]);
        $this->assertTrue($flags[$later->id]);
    }

    public function test_the_profile_splits_upcoming_and_past_on_the_venue_day(): void
    {
        // 12:00 UTC on 2026-11-10 is already the 11th in Auckland (UTC+13)
        config()->set('venue-bookings.timezone', 'Pacific/Auckland');
        $this->travelTo(CarbonImmutable::create(2026, 11, 11, 1, 0, 0, 'Pacific/Auckland'));

        $yesterdayAtVenue = $this->bookingOf($this->user, '20:00', '21:00', '2026-11-10');
        $todayAtVenue = $this->bookingOf($this->user, '20:00', '21:00', '2026-11-11');

        $data = $this->app->make(ProfileBookingsDataProvider::class)->getDataForUser($this->user->id);
        $this->assertNotNull($data);

        $this->assertSame([$todayAtVenue->id], array_column($data['upcoming'], 'id'));
        $this->assertSame([$yesterdayAtVenue->id], array_column($data['past'], 'id'));
    }

    public function test_the_calendar_starts_on_the_venue_day(): void
    {
        $this->get(route('bookings.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('VenueBookings/Index', false)
                ->where('today', '2026-11-10'));

        // At 12:00 UTC on 2026-11-10 it is already the 11th in Auckland (UTC+13)
        config()->set('venue-bookings.timezone', 'Pacific/Auckland');
        $this->travelTo(CarbonImmutable::create(2026, 11, 10, 12, 0, 0, 'UTC'));

        $this->get(route('bookings.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('today', '2026-11-11'));
    }

    /**
     * @return array<string, string>
     */
    private function payload(string $date, string $startTime, string $endTime): array
    {
        return [
            'resource_id' => $this->room->id,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ];
    }

    private function bookingOf(UserModel $user, string $startTime, string $endTime, string $date = '2026-11-10'): BookingModel
    {
        return BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->room->id,
            'user_id' => $user->id,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => 'confirmed',
        ]);
    }

    /**
     * Gives a plain (non-panel) user the permissions through a role of their own.
     *
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
        $this->app->make(AuthorizationServiceInterface::class)->invalidateCache();
    }
}
