<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Unit\Notifications;

use Illuminate\Support\Str;
use Modules\VenueBookings\Filament\Resources\BookingResource;
use Modules\VenueBookings\Notifications\BookingCreatedNotification;
use stdClass;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

final class BookingCreatedNotificationTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([BookingResource::class]);
    }

    public function test_email_links_to_filament_edit_page(): void
    {
        $bookingId = (string) Str::uuid();

        $notification = new BookingCreatedNotification(
            bookingId: $bookingId,
            userName: 'Test User',
            resourceName: 'Test Room',
            date: '01/03/2026',
            startTime: '10:00',
            endTime: '12:00',
        );

        $mail = $notification->toMail(new stdClass);

        $expectedUrl = BookingResource::getUrl('edit', ['record' => $bookingId]);

        $this->assertSame($expectedUrl, $mail->actionUrl);
    }
}
