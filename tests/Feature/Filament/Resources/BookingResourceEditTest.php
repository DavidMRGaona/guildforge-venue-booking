<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Filament\Resources;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\VenueBookings\Domain\Events\BookingConfirmed;
use Modules\VenueBookings\Domain\Events\BookingRejected;
use Modules\VenueBookings\Filament\Resources\BookingResource;
use Modules\VenueBookings\Filament\Resources\BookingResource\Pages\CreateBooking;
use Modules\VenueBookings\Filament\Resources\BookingResource\Pages\EditBooking;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Tests\Support\Modules\ModuleTestCase;

final class BookingResourceEditTest extends ModuleTestCase
{
    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    private BookableResourceModel $bookableResource;

    private UserModel $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Register the BookingResource with the current Filament panel
        // and register its routes so edit/create/index pages are available.
        $panel = Filament::getCurrentPanel();
        $panel->resources([BookingResource::class]);

        // Register resource routes within the panel's name prefix (filament.admin.)
        Route::name($panel->generateRouteName(''))
            ->prefix($panel->getPath())
            ->group(fn () => BookingResource::registerRoutes($panel));

        // Rebuild route collection so module and Filament routes are resolvable by name.
        $router = app('router');
        $oldRoutes = $router->getRoutes();
        $newRoutes = new \Illuminate\Routing\RouteCollection();
        foreach ($oldRoutes->getRoutes() as $route) {
            $newRoutes->add($route);
        }
        $router->setRoutes($newRoutes);

        $this->bookableResource = BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Room',
            'slug' => 'test-room',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $this->admin = UserModel::factory()->admin()->create();

        config()->set('modules.settings.venue-bookings.max_active_bookings_per_user', 10);
        config()->set('modules.settings.venue-bookings.min_advance_minutes', 60);
        config()->set('modules.settings.venue-bookings.max_future_days', 365);

        // Disable predefined fields to avoid validation errors on unrelated form fields.
        config()->set('modules.settings.venue-bookings.predefined_fields', [
            'activity_name' => ['enabled' => false, 'required' => false, 'visibility' => 'public'],
            'num_participants' => ['enabled' => false, 'required' => false, 'visibility' => 'public'],
            'contact_phone' => ['enabled' => false, 'required' => false, 'visibility' => 'public'],
            'notes' => ['enabled' => false, 'required' => false, 'visibility' => 'public'],
        ]);
        config()->set('modules.settings.venue-bookings.custom_fields', []);
    }

    public function test_status_field_is_not_disabled_on_edit_form(): void
    {
        $booking = BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->bookableResource->id,
            'user_id' => $this->admin->id,
            'date' => '2026-03-16',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin);

        Livewire::test(EditBooking::class, ['record' => $booking->id])
            ->assertFormFieldExists('status')
            ->assertFormFieldIsEnabled('status');
    }

    public function test_cancellation_reason_is_editable_for_cancelled_booking(): void
    {
        $booking = BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->bookableResource->id,
            'user_id' => $this->admin->id,
            'date' => '2026-03-16',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'cancelled',
            'cancellation_reason' => 'Original reason',
            'cancelled_at' => now(),
        ]);

        $this->actingAs($this->admin);

        Livewire::test(EditBooking::class, ['record' => $booking->id])
            ->assertFormFieldExists('cancellation_reason')
            ->assertFormFieldIsEnabled('cancellation_reason');
    }

    public function test_changing_status_from_pending_to_confirmed_dispatches_event(): void
    {
        Event::fake();

        $booking = BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->bookableResource->id,
            'user_id' => $this->admin->id,
            'date' => '2026-03-16',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin);

        Livewire::test(EditBooking::class, ['record' => $booking->id])
            ->fillForm([
                'status' => 'confirmed',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        Event::assertDispatched(BookingConfirmed::class);

        $this->assertDatabaseHas('venuebookings_bookings', [
            'id' => $booking->id,
            'status' => 'confirmed',
        ]);

        $booking->refresh();
        $this->assertNotNull($booking->confirmed_at);
    }

    public function test_changing_status_from_pending_to_rejected_with_reason(): void
    {
        Event::fake();

        $booking = BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->bookableResource->id,
            'user_id' => $this->admin->id,
            'date' => '2026-03-16',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin);

        Livewire::test(EditBooking::class, ['record' => $booking->id])
            ->fillForm([
                'status' => 'rejected',
                'cancellation_reason' => 'No valid reason',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        Event::assertDispatched(BookingRejected::class);

        $this->assertDatabaseHas('venuebookings_bookings', [
            'id' => $booking->id,
            'status' => 'rejected',
            'cancellation_reason' => 'No valid reason',
        ]);
    }

    public function test_invalid_status_transition_shows_notification(): void
    {
        $booking = BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->bookableResource->id,
            'user_id' => $this->admin->id,
            'date' => '2026-03-16',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'completed',
        ]);

        $this->actingAs($this->admin);

        Livewire::test(EditBooking::class, ['record' => $booking->id])
            ->fillForm([
                'status' => 'confirmed',
            ])
            ->call('save')
            ->assertNotified();

        $this->assertDatabaseHas('venuebookings_bookings', [
            'id' => $booking->id,
            'status' => 'completed',
        ]);
    }

    public function test_status_field_is_disabled_on_create_form(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateBooking::class)
            ->assertFormFieldExists('status')
            ->assertFormFieldIsDisabled('status');
    }
}
