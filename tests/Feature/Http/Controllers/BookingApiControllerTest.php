<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Tests\Support\Modules\ModuleTestCase;

final class BookingApiControllerTest extends ModuleTestCase
{
    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    private BookableResourceModel $resource;

    protected function setUp(): void
    {
        parent::setUp();

        // Rebuild route collection so module routes are resolvable by name.
        $router = app('router');
        $oldRoutes = $router->getRoutes();
        $newRoutes = new \Illuminate\Routing\RouteCollection();
        foreach ($oldRoutes->getRoutes() as $route) {
            $newRoutes->add($route);
        }
        $router->setRoutes($newRoutes);

        $this->resource = BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Room',
            'slug' => 'test-room',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        config()->set('modules.settings.venue-bookings.max_active_bookings_per_user', 10);
        config()->set('modules.settings.venue-bookings.min_advance_minutes', 60);
        config()->set('modules.settings.venue-bookings.max_future_days', 365);
    }

    public function test_authenticated_user_gets_eligibility_for_future_date(): void
    {
        $user = UserModel::factory()->create();
        $futureDate = date('Y-m-d', strtotime('+7 days'));

        $response = $this->actingAs($user)->getJson(route('bookings.api.eligibility', [
            'resource_id' => $this->resource->id,
            'date' => $futureDate,
        ]));

        $response->assertOk();
        $response->assertJsonStructure(['can_book', 'reasons']);
        $response->assertJson(['can_book' => true, 'reasons' => []]);
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $futureDate = date('Y-m-d', strtotime('+7 days'));

        $response = $this->getJson(route('bookings.api.eligibility', [
            'resource_id' => $this->resource->id,
            'date' => $futureDate,
        ]));

        $response->assertUnauthorized();
    }

    public function test_eligibility_requires_resource_id(): void
    {
        $user = UserModel::factory()->create();

        $response = $this->actingAs($user)->getJson(route('bookings.api.eligibility', [
            'date' => date('Y-m-d', strtotime('+7 days')),
        ]));

        $response->assertUnprocessable();
    }

    public function test_eligibility_requires_date(): void
    {
        $user = UserModel::factory()->create();

        $response = $this->actingAs($user)->getJson(route('bookings.api.eligibility', [
            'resource_id' => $this->resource->id,
        ]));

        $response->assertUnprocessable();
    }

    public function test_slots_rejects_datetime_with_json_error(): void
    {
        $response = $this->getJson(route('bookings.api.slots', [
            'resource_id' => $this->resource->id,
            'date' => '2026-03-01T10:30:00',
        ]));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['date']);
    }
}
