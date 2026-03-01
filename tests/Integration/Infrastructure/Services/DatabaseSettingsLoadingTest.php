<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Integration\Infrastructure\Services;

use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\SettingModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\VenueBookings\Domain\Enums\ApprovalMode;
use Modules\VenueBookings\Infrastructure\Services\BookingSettingsReader;
use Modules\VenueBookings\VenueBookingsServiceProvider;
use Tests\TestCase;

final class DatabaseSettingsLoadingTest extends TestCase
{
    use RefreshDatabase;

    private BookingSettingsReader $reader;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure the service provider is registered (module may not auto-boot in test env)
        $this->app->register(VenueBookingsServiceProvider::class);

        $this->reader = new BookingSettingsReader;
    }

    protected function tearDown(): void
    {
        config()->offsetUnset('modules.settings.venue-bookings');
        config()->offsetUnset('venue-bookings');

        parent::tearDown();
    }

    public function test_settings_reader_reads_from_database_when_settings_loaded(): void
    {
        // Arrange
        SettingModel::create([
            'key' => 'module_settings:venue-bookings',
            'value' => json_encode([
                'approval_mode' => 'auto_confirm',
                'max_active_bookings_per_user' => 19,
            ]),
        ]);

        // Act
        $this->triggerLoadSettingsFromDatabase();

        // Assert
        $this->assertEquals(ApprovalMode::AutoConfirm, $this->reader->getApprovalMode());
        $this->assertEquals(19, $this->reader->getMaxActiveBookings());
    }

    public function test_file_based_settings_remain_as_fallback_when_no_database_row(): void
    {
        // Arrange - set file-based config, no database row
        config()->set('modules.settings.venue-bookings.approval_mode', 'require_approval');

        // Act
        $this->triggerLoadSettingsFromDatabase();

        // Assert - file-based value should remain
        $this->assertEquals(ApprovalMode::RequireApproval, $this->reader->getApprovalMode());
    }

    public function test_database_settings_override_file_based_settings(): void
    {
        // Arrange - set file-based config to one value
        config()->set('modules.settings.venue-bookings.approval_mode', 'require_approval');
        config()->set('modules.settings.venue-bookings.max_active_bookings_per_user', 5);

        // Insert database row with different values
        SettingModel::create([
            'key' => 'module_settings:venue-bookings',
            'value' => json_encode([
                'approval_mode' => 'auto_confirm',
                'max_active_bookings_per_user' => 42,
            ]),
        ]);

        // Act
        $this->triggerLoadSettingsFromDatabase();

        // Assert - database values should win
        $this->assertEquals(ApprovalMode::AutoConfirm, $this->reader->getApprovalMode());
        $this->assertEquals(42, $this->reader->getMaxActiveBookings());
    }

    /**
     * Invoke the private loadSettingsFromDatabase() method on the service provider.
     *
     * Clears the settings cache first so that DB rows inserted in the test
     * are visible to SettingsService (which caches eagerly).
     */
    private function triggerLoadSettingsFromDatabase(): void
    {
        app(SettingsServiceInterface::class)->clearCache();

        $provider = app()->getProvider(VenueBookingsServiceProvider::class);
        $this->assertNotNull($provider, 'VenueBookingsServiceProvider must be registered');

        $reflection = new \ReflectionMethod($provider, 'loadSettingsFromDatabase');
        $reflection->invoke($provider);
    }
}
