<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Filament\Resources;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\VenueBookings\Filament\Resources\BookingResource;
use Modules\VenueBookings\Filament\Resources\BookingResource\Pages\EditBooking;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\OperatingScheduleModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Changing the resource, date or times of a booking in the panel goes through BookingService:
 * an active booking keeps clear of other bookings and fits the schedule, without the settings
 * rules; a finished one is corrected freely.
 */
final class BookingResourceMoveTest extends ModuleTestCase
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

        $this->bookableResource = $this->createResource('test-room');

        $this->admin = UserModel::factory()->admin()->create();
        $this->member = UserModel::factory()->create();

        config()->set('modules.settings.venue-bookings.max_active_bookings_per_user', 1);
        config()->set('modules.settings.venue-bookings.min_advance_minutes', 120);
        config()->set('modules.settings.venue-bookings.max_future_days', 30);
        config()->set('modules.settings.venue-bookings.predefined_fields', [
            'activity_name' => ['enabled' => false, 'required' => false, 'visibility' => 'public'],
            'num_participants' => ['enabled' => false, 'required' => false, 'visibility' => 'public'],
            'contact_phone' => ['enabled' => false, 'required' => false, 'visibility' => 'public'],
            'notes' => ['enabled' => false, 'required' => false, 'visibility' => 'public'],
        ]);
        config()->set('modules.settings.venue-bookings.custom_fields', []);
    }

    public function test_it_moves_an_active_booking_to_a_free_range(): void
    {
        $booking = $this->createBookingOf($this->member, '2026-03-16', '10:00', '12:00');
        $this->actingAs($this->admin);

        Livewire::test(EditBooking::class, ['record' => $booking])
            ->fillForm(['date' => '2026-03-17', 'start_time' => '14:00', 'end_time' => '16:00', 'admin_notes' => 'Cambio pedido por el grupo'])
            ->call('save')
            ->assertHasNoFormErrors();

        $booking = BookingModel::query()->findOrFail($booking);
        $this->assertSame('2026-03-17', $booking->date->format('Y-m-d'));
        $this->assertSame('14:00', substr($booking->start_time, 0, 5));
        $this->assertSame('16:00', substr($booking->end_time, 0, 5));
        $this->assertSame('Cambio pedido por el grupo', $booking->admin_notes);
        $this->assertSame('confirmed', $booking->status->value);
    }

    public function test_it_refuses_a_move_onto_another_booking(): void
    {
        $booking = $this->createBookingOf($this->member, '2026-03-16', '10:00', '12:00');
        $this->createBookingOf($this->admin, '2026-03-17', '14:00', '16:00');
        $this->actingAs($this->admin);

        Livewire::test(EditBooking::class, ['record' => $booking])
            ->fillForm(['date' => '2026-03-17', 'start_time' => '15:00', 'end_time' => '17:00', 'admin_notes' => 'No debe guardarse'])
            ->call('save')
            ->assertHasFormErrors(['start_time']);

        $booking = BookingModel::query()->findOrFail($booking);
        $this->assertSame('2026-03-16', $booking->date->format('Y-m-d'));
        $this->assertNull($booking->admin_notes);
    }

    public function test_a_booking_may_grow_over_its_own_range(): void
    {
        $booking = $this->createBookingOf($this->member, '2026-03-16', '10:00', '12:00');
        $this->actingAs($this->admin);

        Livewire::test(EditBooking::class, ['record' => $booking])
            ->fillForm(['start_time' => '09:00', 'end_time' => '13:00'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('13:00', substr(BookingModel::query()->findOrFail($booking)->end_time, 0, 5));
    }

    public function test_it_refuses_a_range_outside_the_schedule(): void
    {
        $booking = $this->createBookingOf($this->member, '2026-03-16', '10:00', '12:00');
        $this->actingAs($this->admin);

        Livewire::test(EditBooking::class, ['record' => $booking])
            ->fillForm(['start_time' => '21:00', 'end_time' => '23:00'])
            ->call('save')
            ->assertHasFormErrors(['start_time']);
    }

    public function test_the_settings_rules_do_not_bind_a_move(): void
    {
        // Beyond the 30 days ahead, and the member already holds the one active booking allowed
        $booking = $this->createBookingOf($this->member, '2026-03-16', '10:00', '12:00');
        $this->actingAs($this->admin);

        Livewire::test(EditBooking::class, ['record' => $booking])
            ->fillForm(['date' => '2026-04-20'])
            ->call('save')
            ->assertHasNoFormErrors();

        // Within the two hours of advance (09:00 now)
        Livewire::test(EditBooking::class, ['record' => $booking])
            ->fillForm(['date' => '2026-03-01', 'start_time' => '10:00', 'end_time' => '11:00'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('2026-03-01', BookingModel::query()->findOrFail($booking)->date->format('Y-m-d'));
    }

    public function test_it_refuses_a_new_start_that_has_passed(): void
    {
        // 09:00 now
        $booking = $this->createBookingOf($this->member, '2026-03-16', '10:00', '12:00');
        $this->actingAs($this->admin);

        Livewire::test(EditBooking::class, ['record' => $booking])
            ->fillForm(['date' => '2026-03-01', 'start_time' => '08:00', 'end_time' => '10:00'])
            ->call('save')
            ->assertHasFormErrors(['start_time']);
    }

    public function test_a_booking_under_way_may_be_lengthened_or_change_room(): void
    {
        $booking = $this->createBookingOf($this->member, '2026-03-01', '08:00', '10:00');
        $otherRoom = $this->createResource('other-room');
        $this->actingAs($this->admin);

        Livewire::test(EditBooking::class, ['record' => $booking])
            ->fillForm(['end_time' => '11:00'])
            ->call('save')
            ->assertHasNoFormErrors();

        Livewire::test(EditBooking::class, ['record' => $booking])
            ->fillForm(['resource_id' => $otherRoom->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $booking = BookingModel::query()->findOrFail($booking);
        $this->assertSame($otherRoom->id, $booking->resource_id);
        $this->assertSame('11:00', substr($booking->end_time, 0, 5));
    }

    public function test_it_refuses_a_move_to_an_inactive_resource(): void
    {
        $booking = $this->createBookingOf($this->member, '2026-03-16', '10:00', '12:00');
        $closed = $this->createResource('closed-room', status: 'inactive');
        $this->actingAs($this->admin);

        Livewire::test(EditBooking::class, ['record' => $booking])
            ->fillForm(['resource_id' => $closed->id])
            ->call('save')
            ->assertHasFormErrors(['resource_id']);
    }

    public function test_a_finished_booking_is_corrected_freely(): void
    {
        // Cancelled, so it takes up no slot: moved onto another booking and into the past
        $booking = $this->createBookingOf($this->member, '2026-03-16', '10:00', '12:00', 'cancelled');
        $this->createBookingOf($this->admin, '2026-02-20', '10:00', '12:00');
        $this->actingAs($this->admin);

        Livewire::test(EditBooking::class, ['record' => $booking])
            ->fillForm(['date' => '2026-02-20', 'start_time' => '10:30', 'end_time' => '11:30'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('2026-02-20', BookingModel::query()->findOrFail($booking)->date->format('Y-m-d'));
    }

    public function test_editing_other_fields_of_a_booking_under_way_runs_no_check(): void
    {
        $booking = $this->createBookingOf($this->member, '2026-03-01', '08:00', '10:00');
        $this->actingAs($this->admin);

        Livewire::test(EditBooking::class, ['record' => $booking])
            ->fillForm(['admin_notes' => 'Llegan tarde'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Llegan tarde', BookingModel::query()->findOrFail($booking)->admin_notes);
    }

    private function createResource(string $slug, string $status = 'active'): BookableResourceModel
    {
        $resource = BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => $slug,
            'slug' => $slug,
            'status' => $status,
            'sort_order' => 0,
        ]);

        // Open every day 08:00-22:00 in hourly slots
        OperatingScheduleModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $resource->id,
            'slot_duration_minutes' => 60,
            'min_consecutive_slots' => 1,
            'max_consecutive_slots' => 6,
            'day_schedules' => array_map(
                fn (int $day): array => ['day_of_week' => $day, 'open_time' => '08:00', 'close_time' => '22:00', 'is_enabled' => true],
                range(0, 6),
            ),
        ]);

        return $resource;
    }

    private function createBookingOf(UserModel $user, string $date, string $startTime, string $endTime, string $status = 'confirmed'): string
    {
        $id = (string) Str::uuid();
        BookingModel::create([
            'id' => $id,
            'resource_id' => $this->bookableResource->id,
            'user_id' => $user->id,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => $status,
        ]);

        return $id;
    }
}
