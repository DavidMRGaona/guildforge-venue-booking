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
        $this->registerProvider();

        // Assert
        $this->assertEquals(ApprovalMode::AutoConfirm, $this->reader->getApprovalMode());
        $this->assertEquals(19, $this->reader->getMaxActiveBookings());
    }

    public function test_shipped_defaults_apply_when_no_database_row(): void
    {
        // Act
        $this->registerProvider();

        // Assert - config/settings.php values
        $this->assertEquals(ApprovalMode::RequireApproval, $this->reader->getApprovalMode());
        $this->assertEquals(3, $this->reader->getMaxActiveBookings());
    }

    public function test_keys_missing_from_the_stored_settings_keep_their_shipped_defaults(): void
    {
        // Arrange - saved by a release without the auto-completion keys and with fewer notification keys
        SettingModel::create([
            'key' => 'module_settings:venue-bookings',
            'value' => json_encode([
                'approval_mode' => 'auto_confirm',
                'notifications' => ['notify_on_booking_created' => false],
            ]),
        ]);

        // Act
        $this->registerProvider();

        // Assert - stored values win, the rest come from config/settings.php
        $this->assertEquals(ApprovalMode::AutoConfirm, $this->reader->getApprovalMode());
        $this->assertTrue(config('modules.settings.venue-bookings.auto_complete_enabled'));
        $this->assertSame(24, config('modules.settings.venue-bookings.auto_complete_after_hours'));
        $this->assertFalse(config('modules.settings.venue-bookings.notifications.notify_on_booking_created'));
        $this->assertTrue(config('modules.settings.venue-bookings.notifications.notify_on_booking_reminder'));
        $this->assertFalse(config('modules.settings.venue-bookings.predefined_fields.notes.enabled'));
    }

    /**
     * Run the provider's register() as every request does, once the rows inserted in the test
     * are visible to SettingsService (which caches eagerly).
     */
    private function registerProvider(): void
    {
        app(SettingsServiceInterface::class)->clearCache();
        config()->offsetUnset('modules.settings.venue-bookings');

        $provider = app()->getProvider(VenueBookingsServiceProvider::class);
        $this->assertNotNull($provider, 'VenueBookingsServiceProvider must be registered');

        $provider->register();
    }
}
