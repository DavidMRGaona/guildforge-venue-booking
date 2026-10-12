<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Tests\Support\Modules\ModuleTestCase;

final class VenueBookingsSeoTest extends ModuleTestCase
{
    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('es');
        config(['app.name' => 'Tolerol']);
    }

    public function test_booking_calendar_has_its_title(): void
    {
        $this->get('/reservas')->assertSee('<title>Reservas del local - Tolerol</title>', false);
    }

    public function test_my_bookings_is_noindex(): void
    {
        $this->actingAs(UserModel::factory()->create(['email_verified_at' => now()]))
            ->get('/reservas/mis-reservas')
            ->assertSee('<meta name="robots" content="noindex, nofollow" data-seo-server>', false);
    }
}
