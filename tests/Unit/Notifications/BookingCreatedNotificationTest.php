<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Unit\Notifications;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Modules\VenueBookings\Filament\Resources\BookingResource;
use Modules\VenueBookings\Notifications\BookingCreatedNotification;
use Tests\Support\Modules\ModuleTestCase;

final class BookingCreatedNotificationTest extends ModuleTestCase
{
    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $panel = Filament::getCurrentPanel();
        $panel->resources([BookingResource::class]);

        Route::name($panel->generateRouteName(''))
            ->prefix($panel->getPath())
            ->group(fn () => BookingResource::registerRoutes($panel));

        $router = app('router');
        $oldRoutes = $router->getRoutes();
        $newRoutes = new \Illuminate\Routing\RouteCollection();
        foreach ($oldRoutes->getRoutes() as $route) {
            $newRoutes->add($route);
        }
        $router->setRoutes($newRoutes);
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

        $mail = $notification->toMail(new \stdClass);

        $expectedUrl = BookingResource::getUrl('edit', ['record' => $bookingId]);

        $this->assertSame($expectedUrl, $mail->actionUrl);
    }
}
