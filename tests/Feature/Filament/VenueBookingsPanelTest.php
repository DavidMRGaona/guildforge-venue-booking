<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Filament;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\VenueBookings\Filament\Pages\VenueBookingsSettings;
use Modules\VenueBookings\Filament\Resources\BookableResourceResource;
use Modules\VenueBookings\Filament\Resources\BookableResourceResource\Pages\CreateBookableResource;
use Modules\VenueBookings\Filament\Resources\BookableResourceResource\Pages\EditBookableResource;
use Modules\VenueBookings\Filament\Resources\BookableResourceResource\Pages\ListBookableResources;
use Modules\VenueBookings\Filament\Resources\BookingResource;
use Modules\VenueBookings\Filament\Resources\BookingResource\Pages\ListBookings;
use Modules\VenueBookings\Filament\Widgets\TodayBookingsWidget;
use Modules\VenueBookings\Filament\Widgets\UpcomingBookingsWidget;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

final class VenueBookingsPanelTest extends ModuleTestCase
{
    use CreatesUsersWithPermissions;
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    private UserModel $admin;

    private BookableResourceModel $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([BookableResourceResource::class, BookingResource::class]);
        $this->admin = $this->actingAsPanelAdmin();
        $this->room = BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sala grande',
            'slug' => 'sala-grande',
            'status' => 'active',
            'sort_order' => 0,
        ]);
    }

    public function test_bookable_resource_pages_render(): void
    {
        Livewire::test(ListBookableResources::class)->assertOk()->assertCanSeeTableRecords([$this->room]);
        Livewire::test(CreateBookableResource::class)->assertOk();
        Livewire::test(EditBookableResource::class, ['record' => $this->room->getRouteKey()])
            ->assertOk()
            ->assertFormSet(['name' => 'Sala grande']);
    }

    public function test_booking_list_shows_bookings(): void
    {
        $booking = BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->room->id,
            'user_id' => $this->admin->id,
            'date' => now()->addDays(3)->toDateString(),
            'start_time' => '18:00',
            'end_time' => '20:00',
            'status' => 'pending',
        ]);

        Livewire::test(ListBookings::class)->assertOk()->assertCanSeeTableRecords([$booking]);
    }

    public function test_widgets_render(): void
    {
        foreach ([TodayBookingsWidget::class, UpcomingBookingsWidget::class] as $widget) {
            Livewire::test($widget)->assertOk();
        }
    }

    public function test_the_settings_page_follows_the_settings_permission(): void
    {
        $this->assertTrue(VenueBookingsSettings::canAccess(), 'Admins must keep access');

        $this->actingAs($this->editorWithPermissions(['venuebookings:bookings.settings']));
        $this->assertTrue(VenueBookingsSettings::canAccess());

        $this->actingAs($this->editorWithPermissions(['venuebookings:bookings.view_any']));
        $this->assertFalse(VenueBookingsSettings::canAccess());
    }
}
