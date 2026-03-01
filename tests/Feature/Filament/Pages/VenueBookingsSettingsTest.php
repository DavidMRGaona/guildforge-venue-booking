<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Filament\Pages;

use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\SettingModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Modules\VenueBookings\Filament\Pages\VenueBookingsSettings;
use Tests\Support\Modules\ModuleTestCase;

final class VenueBookingsSettingsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    private UserModel $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Register the settings page with Filament panel and its routes
        $panel = Filament::getCurrentPanel();
        $panel->pages([VenueBookingsSettings::class]);

        Route::name($panel->generateRouteName(''))
            ->prefix($panel->getPath())
            ->group(fn () => VenueBookingsSettings::registerRoutes($panel));

        // Rebuild route collection
        $router = app('router');
        $oldRoutes = $router->getRoutes();
        $newRoutes = new \Illuminate\Routing\RouteCollection;
        foreach ($oldRoutes->getRoutes() as $route) {
            $newRoutes->add($route);
        }
        $router->setRoutes($newRoutes);

        $this->admin = UserModel::factory()->admin()->create();
    }

    public function test_settings_page_saves_to_database(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(VenueBookingsSettings::class)
            ->fillForm([
                'approval_mode' => 'auto_confirm',
                'max_active_bookings_per_user' => 10,
                'min_advance_minutes' => 30,
                'max_future_days' => 14,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // Assert the settings were saved to the database
        $this->assertDatabaseHas('settings', [
            'key' => 'module_settings:venue-bookings',
        ]);

        $row = SettingModel::where('key', 'module_settings:venue-bookings')->first();
        $this->assertNotNull($row);

        $decoded = json_decode((string) $row->value, true);
        $this->assertIsArray($decoded);
        $this->assertEquals('auto_confirm', $decoded['approval_mode']);
        $this->assertEquals(10, $decoded['max_active_bookings_per_user']);
        $this->assertEquals(30, $decoded['min_advance_minutes']);
        $this->assertEquals(14, $decoded['max_future_days']);
    }

    public function test_settings_page_loads_from_database(): void
    {
        $this->actingAs($this->admin);

        // Pre-populate DB with settings
        SettingModel::create([
            'key' => 'module_settings:venue-bookings',
            'value' => json_encode([
                'approval_mode' => 'auto_confirm',
                'max_active_bookings_per_user' => 25,
                'min_advance_minutes' => 45,
                'max_future_days' => 60,
            ]),
        ]);

        // Clear cache so mount() reads fresh data
        app(SettingsServiceInterface::class)->clearCache();

        $component = Livewire::test(VenueBookingsSettings::class);

        $component->assertFormSet([
            'approval_mode' => 'auto_confirm',
            'max_active_bookings_per_user' => 25,
            'min_advance_minutes' => 45,
            'max_future_days' => 60,
        ]);
    }

    public function test_settings_page_updates_in_memory_config_after_save(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(VenueBookingsSettings::class)
            ->fillForm([
                'approval_mode' => 'auto_confirm',
                'max_active_bookings_per_user' => 7,
                'min_advance_minutes' => 60,
                'max_future_days' => 30,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // Config should be updated for the current request
        $this->assertEquals('auto_confirm', config('modules.settings.venue-bookings.approval_mode'));
        $this->assertEquals(7, config('modules.settings.venue-bookings.max_active_bookings_per_user'));
    }
}
