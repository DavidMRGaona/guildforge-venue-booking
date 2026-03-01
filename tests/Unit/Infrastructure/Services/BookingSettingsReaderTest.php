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
        config()->offsetUnset('venue-bookings');
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
        // Both config keys are null — this is the bug scenario.
        // The safe default must be 'require_approval' (not 'auto_confirm').
        config()->offsetUnset('modules.settings.venue-bookings');
        config()->offsetUnset('venue-bookings');

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
        config()->offsetUnset('venue-bookings');

        $this->assertEquals(3, $this->reader->getMaxActiveBookings());
    }
}
