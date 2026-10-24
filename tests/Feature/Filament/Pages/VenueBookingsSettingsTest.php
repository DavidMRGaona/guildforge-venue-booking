<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Filament\Pages;

use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\SettingModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Livewire\Livewire;
use Modules\VenueBookings\Filament\Pages\VenueBookingsSettings;
use Modules\VenueBookings\VenueBookingsServiceProvider;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

final class VenueBookingsSettingsTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    private UserModel $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel(pages: [VenueBookingsSettings::class]);

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

    public function test_auto_completion_shows_its_defaults_when_the_saved_settings_predate_it(): void
    {
        $this->actingAs($this->admin);

        // Settings saved by a release without the auto-completion keys
        SettingModel::create([
            'key' => 'module_settings:venue-bookings',
            'value' => json_encode(['approval_mode' => 'auto_confirm', 'max_active_bookings_per_user' => 3, 'min_advance_minutes' => 60, 'max_future_days' => 30]),
        ]);
        app(SettingsServiceInterface::class)->clearCache();

        // Load them as a production request does: the provider registers with the row
        // already in the database, and the keys the row lacks keep their shipped defaults
        $this->registerProviderWithStoredSettings();
        $this->assertTrue(config('modules.settings.venue-bookings.auto_complete_enabled'));
        $this->assertSame('auto_confirm', config('modules.settings.venue-bookings.approval_mode'));

        Livewire::test(VenueBookingsSettings::class)
            ->assertFormSet([
                'auto_complete_enabled' => true,
                'auto_complete_after_hours' => 24,
            ])
            ->assertSee('Completar reservas automáticamente')
            ->assertSee('Horas tras el fin para completar')
            ->call('save')
            ->assertHasNoFormErrors();

        $decoded = json_decode((string) SettingModel::where('key', 'module_settings:venue-bookings')->value('value'), true);
        $this->assertIsArray($decoded);
        $this->assertTrue($decoded['auto_complete_enabled']);
        $this->assertEquals(24, $decoded['auto_complete_after_hours']);
        $this->assertSame('auto_confirm', $decoded['approval_mode']);
    }

    public function test_auto_completion_settings_are_saved(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(VenueBookingsSettings::class)
            ->fillForm(['auto_complete_enabled' => true, 'auto_complete_after_hours' => 48])
            ->call('save')
            ->assertHasNoFormErrors();

        $decoded = json_decode((string) SettingModel::where('key', 'module_settings:venue-bookings')->value('value'), true);
        $this->assertIsArray($decoded);
        $this->assertTrue($decoded['auto_complete_enabled']);
        $this->assertEquals(48, $decoded['auto_complete_after_hours']);
    }

    public function test_auto_completion_hours_are_validated_on_the_server(): void
    {
        $this->actingAs($this->admin);

        foreach ([721, -1, 1.5, null] as $invalid) {
            Livewire::test(VenueBookingsSettings::class)
                ->fillForm(['auto_complete_enabled' => true, 'auto_complete_after_hours' => $invalid])
                ->call('save')
                ->assertHasFormErrors(['auto_complete_after_hours']);
        }

        foreach ([0, 720] as $valid) {
            Livewire::test(VenueBookingsSettings::class)
                ->fillForm(['auto_complete_enabled' => true, 'auto_complete_after_hours' => $valid])
                ->call('save')
                ->assertHasNoFormErrors();
        }
    }

    public function test_the_hours_field_shows_only_while_auto_completion_is_on(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(VenueBookingsSettings::class)
            ->fillForm(['auto_complete_enabled' => true])
            ->assertFormFieldVisible('auto_complete_after_hours')
            ->fillForm(['auto_complete_enabled' => false])
            ->assertFormFieldHidden('auto_complete_after_hours')
            ->call('save')
            ->assertHasNoFormErrors();

        $decoded = json_decode((string) SettingModel::where('key', 'module_settings:venue-bookings')->value('value'), true);
        $this->assertIsArray($decoded);
        $this->assertFalse($decoded['auto_complete_enabled']);
    }

    /**
     * Run the module provider's register() again, as every production request does after the
     * row exists: the core puts the shipped defaults with the stored values on top.
     */
    private function registerProviderWithStoredSettings(): void
    {
        $provider = $this->app->getProvider(VenueBookingsServiceProvider::class);
        $this->assertNotNull($provider, 'VenueBookingsServiceProvider must be registered');

        $provider->register();
    }
}
