<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Unit\Infrastructure\Services;

use Modules\VenueBookings\Domain\Enums\ApprovalMode;
use Modules\VenueBookings\Infrastructure\Services\BookingSettingsReader;
use Tests\TestCase;

final class BookingSettingsReaderTest extends TestCase
{
    private BookingSettingsReader $reader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reader = new BookingSettingsReader;
    }

    protected function tearDown(): void
    {
        // Reset config keys to avoid bleeding between tests
        config()->offsetUnset('modules.settings.venue-bookings');
        parent::tearDown();
    }

    public function test_requires_approval_returns_true_when_config_is_require_approval(): void
    {
        config()->set('modules.settings.venue-bookings.approval_mode', 'require_approval');

        $this->assertTrue($this->reader->requiresApproval());
        $this->assertEquals(ApprovalMode::RequireApproval, $this->reader->getApprovalMode());
    }

    public function test_requires_approval_returns_false_when_config_is_auto_confirm(): void
    {
        config()->set('modules.settings.venue-bookings.approval_mode', 'auto_confirm');

        $this->assertFalse($this->reader->requiresApproval());
        $this->assertEquals(ApprovalMode::AutoConfirm, $this->reader->getApprovalMode());
    }

    public function test_requires_approval_defaults_to_true_when_config_key_is_missing(): void
    {
        // Settings not loaded: the safe default of config/settings.php is 'require_approval'
        config()->offsetUnset('modules.settings.venue-bookings');

        $this->assertTrue($this->reader->requiresApproval());
        $this->assertEquals(ApprovalMode::RequireApproval, $this->reader->getApprovalMode());
    }

    public function test_get_max_active_bookings_returns_value_from_config(): void
    {
        config()->set('modules.settings.venue-bookings.max_active_bookings_per_user', 5);

        $this->assertEquals(5, $this->reader->getMaxActiveBookings());
    }

    public function test_get_max_active_bookings_returns_default_when_config_missing(): void
    {
        config()->offsetUnset('modules.settings.venue-bookings');

        $this->assertEquals(3, $this->reader->getMaxActiveBookings());
    }

    public function test_reminders_follow_the_panel_toggle(): void
    {
        config()->set('modules.settings.venue-bookings.notifications.notify_on_booking_reminder', false);

        $this->assertFalse($this->reader->isReminderEnabled());

        config()->set('modules.settings.venue-bookings.notifications.notify_on_booking_reminder', true);

        $this->assertTrue($this->reader->isReminderEnabled());
    }

    public function test_reminders_are_enabled_by_default(): void
    {
        config()->offsetUnset('modules.settings.venue-bookings');

        $this->assertTrue($this->reader->isReminderEnabled());
    }

    public function test_the_venue_timezone_comes_from_the_module_settings(): void
    {
        config()->set('modules.settings.venue-bookings.timezone', 'Atlantic/Canary');

        $this->assertSame('Atlantic/Canary', $this->reader->getDateTimeZone()->getName());
    }

    public function test_the_venue_timezone_falls_back_to_the_app_timezone(): void
    {
        config()->offsetUnset('modules.settings.venue-bookings');
        config()->set('app.timezone', 'America/Bogota');

        $this->assertSame('America/Bogota', $this->reader->getDateTimeZone()->getName());
    }

    public function test_auto_completion_is_on_after_24_hours_by_default(): void
    {
        config()->offsetUnset('modules.settings.venue-bookings');

        $this->assertTrue($this->reader->isAutoCompleteEnabled());
        $this->assertSame(24, $this->reader->getAutoCompleteAfterHours());
    }

    public function test_auto_completion_reads_the_panel_values(): void
    {
        config()->set('modules.settings.venue-bookings.auto_complete_enabled', false);
        config()->set('modules.settings.venue-bookings.auto_complete_after_hours', '48');

        $this->assertFalse($this->reader->isAutoCompleteEnabled());
        $this->assertSame(48, $this->reader->getAutoCompleteAfterHours());
    }

    public function test_auto_completion_hours_stay_between_0_and_720(): void
    {
        config()->set('modules.settings.venue-bookings.auto_complete_after_hours', -5);
        $this->assertSame(0, $this->reader->getAutoCompleteAfterHours());

        config()->set('modules.settings.venue-bookings.auto_complete_after_hours', 5000);
        $this->assertSame(720, $this->reader->getAutoCompleteAfterHours());

        config()->set('modules.settings.venue-bookings.auto_complete_after_hours', 0);
        $this->assertSame(0, $this->reader->getAutoCompleteAfterHours());
    }

    public function test_without_loaded_settings_every_value_comes_from_config_settings(): void
    {
        $defaults = require dirname(__DIR__, 4).'/config/settings.php';
        config()->offsetUnset('modules.settings.venue-bookings');

        $this->assertSame($defaults['approval_mode'], $this->reader->getApprovalMode()->value);
        $this->assertSame($defaults['max_active_bookings_per_user'], $this->reader->getMaxActiveBookings());
        $this->assertSame($defaults['min_advance_minutes'], $this->reader->getMinAdvanceMinutes());
        $this->assertSame($defaults['max_future_days'], $this->reader->getMaxFutureDays());
        $this->assertSame($defaults['auto_complete_enabled'], $this->reader->isAutoCompleteEnabled());
        $this->assertSame($defaults['auto_complete_after_hours'], $this->reader->getAutoCompleteAfterHours());
        $this->assertSame($defaults['time_block_presets'], $this->reader->getTimeBlockPresets());
        $this->assertSame($defaults['notifications']['reminder_hours_before'], $this->reader->getReminderHoursBefore());
        $this->assertSame($defaults['notifications']['notify_on_booking_created'], $this->reader->isNotifyOnBookingCreatedEnabled());
        $this->assertSame($defaults['notifications']['notify_on_booking_received'], $this->reader->isNotifyOnBookingReceivedEnabled());
        $this->assertSame($defaults['notifications']['notify_on_booking_confirmed'], $this->reader->isNotifyOnBookingConfirmedEnabled());
        $this->assertSame($defaults['notifications']['notify_on_booking_cancelled'], $this->reader->isNotifyOnBookingCancelledEnabled());
        $this->assertSame($defaults['notifications']['notify_on_booking_rejected'], $this->reader->isNotifyOnBookingRejectedEnabled());
        $this->assertSame($defaults['notifications']['notify_on_booking_reminder'], $this->reader->isReminderEnabled());
        $this->assertSame($defaults['associations']['game_tables_visibility'], $this->reader->get('associations.game_tables_visibility'));
    }

    public function test_a_loaded_value_wins_over_its_default_even_when_false(): void
    {
        config()->set('modules.settings.venue-bookings.notifications.notify_on_booking_created', false);
        config()->set('modules.settings.venue-bookings.time_block_presets', []);

        $this->assertFalse($this->reader->isNotifyOnBookingCreatedEnabled());
        $this->assertSame([], $this->reader->getTimeBlockPresets());
        // Keys not loaded keep their default
        $this->assertTrue($this->reader->isNotifyOnBookingConfirmedEnabled());
    }

    public function test_it_refuses_a_setting_missing_from_config_settings(): void
    {
        $this->expectException(\LogicException::class);

        $this->reader->get('notifications.notify_on_something_else');
    }

    public function test_every_setting_the_code_reads_is_declared_in_config_settings(): void
    {
        $read = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 4).'/src')) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                preg_match_all("/(?:settings|this)->get\\('([a-z_.]+)'\\)/", (string) file_get_contents($file->getPathname()), $matches);
                $read = [...$read, ...$matches[1]];
            }
        }

        $this->assertNotEmpty($read);

        foreach (array_unique($read) as $key) {
            // get() throws for a key config/settings.php does not declare
            $this->reader->get($key);
        }

        $this->addToAssertionCount(count(array_unique($read)));
    }
}
