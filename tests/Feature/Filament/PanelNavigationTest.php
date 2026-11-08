<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Filament;

use App\Filament\Clusters\SettingsCluster;
use App\Filament\Support\PanelNavigationGroup;
use Modules\VenueBookings\Filament\Pages\VenueBookingsSettings;
use Modules\VenueBookings\Filament\Resources\BookableResourceResource;
use Modules\VenueBookings\Filament\Resources\BookingResource;
use Modules\VenueBookings\VenueBookingsServiceProvider;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The panel menu is grouped by task (core#409): bookings live in «Actividades» with the spaces
 * under them, and the module settings join the «Configuración» cluster.
 */
final class PanelNavigationTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel(
            [BookableResourceResource::class, BookingResource::class],
            [VenueBookingsSettings::class],
        );
        $this->actingAsPanelAdmin();
    }

    public function test_bookings_sit_in_the_activities_group_in_their_own_slot(): void
    {
        $this->assertSame(PanelNavigationGroup::Activities->getLabel(), BookingResource::getNavigationGroup());
        $this->assertSame(30, BookingResource::getNavigationSort());
        $this->assertSame('Reservas', BookingResource::getNavigationLabel());
        $this->assertNull(BookingResource::getNavigationParentItem());
    }

    public function test_spaces_are_a_child_of_bookings_in_the_same_group(): void
    {
        $this->assertSame(PanelNavigationGroup::Activities->getLabel(), BookableResourceResource::getNavigationGroup());
        $this->assertSame(BookingResource::getNavigationLabel(), BookableResourceResource::getNavigationParentItem());
        $this->assertSame('Espacios', BookableResourceResource::getNavigationLabel());
    }

    public function test_the_settings_join_the_settings_cluster(): void
    {
        $this->assertSame(SettingsCluster::class, VenueBookingsSettings::getCluster());
        $this->assertSame(160, VenueBookingsSettings::getNavigationSort());
        $this->assertSame('Reservas', VenueBookingsSettings::getNavigationLabel());
        $this->assertNull(VenueBookingsSettings::getNavigationGroup());
        $this->assertSame('Sistema', SettingsCluster::getNavigationGroup());
        $this->assertStringStartsWith('/admin/configuracion/', (string) parse_url(VenueBookingsSettings::getUrl(), PHP_URL_PATH));
    }

    public function test_the_module_declares_no_navigation_group_of_its_own(): void
    {
        $provider = new VenueBookingsServiceProvider($this->app);

        $this->assertSame([], $provider->registerNavigationGroups());
    }

    public function test_the_panel_menu_shows_bookings_under_the_activities_group(): void
    {
        $this->get(BookingResource::getUrl())
            ->assertOk()
            ->assertSeeInOrder([PanelNavigationGroup::Activities->getLabel(), 'Reservas'])
            ->assertDontSee('Reservas del local');
    }
}
