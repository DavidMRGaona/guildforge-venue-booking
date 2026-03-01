<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Repositories\EloquentBookingRepository;
use Modules\VenueBookings\Infrastructure\Services\BookingEligibilityService;
use Modules\VenueBookings\Infrastructure\Services\BookingSettingsReader;
use Tests\TestCase;

final class BookingEligibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private BookingEligibilityService $service;

    private BookableResourceModel $resource;

    private UserModel $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new BookingEligibilityService(
            new EloquentBookingRepository(),
            new BookingSettingsReader(),
        );

        $this->resource = BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Room',
            'slug' => 'test-room',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $this->user = UserModel::factory()->create();

        // Set permissive defaults so tests pass by default
        config()->set('venue-bookings.max_active_bookings_per_user', 10);
        config()->set('venue-bookings.min_advance_minutes', 0);
        config()->set('venue-bookings.max_future_days', 365);
    }

    public function test_it_returns_eligible_when_all_checks_pass(): void
    {
        // Date 7 days from now - well within limits
        $date = date('Y-m-d', strtotime('+7 days'));

        $result = $this->service->canUserBook($this->user->id, $this->resource->id, $date);

        $this->assertTrue($result->canBook);
        $this->assertEmpty($result->reasons);
    }

    public function test_it_returns_ineligible_when_booking_limit_exceeded(): void
    {
        config()->set('venue-bookings.max_active_bookings_per_user', 2);

        // Create 2 active bookings (pending + confirmed)
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-04-01',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'pending',
        ]);

        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-04-02',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'confirmed',
        ]);

        $date = date('Y-m-d', strtotime('+7 days'));

        $result = $this->service->canUserBook($this->user->id, $this->resource->id, $date);

        $this->assertFalse($result->canBook);
        $this->assertNotEmpty($result->reasons);
    }

    public function test_it_returns_ineligible_when_date_too_far_in_future(): void
    {
        config()->set('venue-bookings.max_future_days', 30);

        // Date 60 days from now - beyond the 30-day limit
        $date = date('Y-m-d', strtotime('+60 days'));

        $result = $this->service->canUserBook($this->user->id, $this->resource->id, $date);

        $this->assertFalse($result->canBook);
        $this->assertNotEmpty($result->reasons);
    }

    public function test_it_returns_ineligible_when_booking_too_soon(): void
    {
        config()->set('venue-bookings.min_advance_minutes', 1440); // 24 hours

        // Date in the past - always too soon
        $date = date('Y-m-d', strtotime('-1 day'));

        $result = $this->service->canUserBook($this->user->id, $this->resource->id, $date);

        $this->assertFalse($result->canBook);
        $this->assertNotEmpty($result->reasons);
    }

    public function test_it_returns_ineligible_with_multiple_reasons(): void
    {
        config()->set('venue-bookings.max_active_bookings_per_user', 1);
        config()->set('venue-bookings.max_future_days', 7);

        // Create 1 active booking to exceed the limit
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-04-01',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'pending',
        ]);

        // Date 30 days from now - exceeds max_future_days of 7
        $date = date('Y-m-d', strtotime('+30 days'));

        $result = $this->service->canUserBook($this->user->id, $this->resource->id, $date);

        $this->assertFalse($result->canBook);
        $this->assertCount(2, $result->reasons);
    }

    public function test_future_date_with_advance_setting_returns_eligible(): void
    {
        config()->set('venue-bookings.min_advance_minutes', 60);

        // Date 2 days in the future — well beyond 60 minutes
        $date = date('Y-m-d', strtotime('+2 days'));

        $result = $this->service->canUserBook($this->user->id, $this->resource->id, $date);

        $this->assertTrue($result->canBook, 'A date 2 days in the future should be eligible with 60 min advance setting');
        $this->assertEmpty($result->reasons);
    }

    public function test_today_date_with_advance_setting_is_ineligible(): void
    {
        config()->set('venue-bookings.min_advance_minutes', 60);

        // Today's date — strtotime gives midnight, which is always before now + 60 min
        $date = now()->toDateString();

        $result = $this->service->canUserBook($this->user->id, $this->resource->id, $date);

        // Today at midnight is always in the past, so booking "today" with 60 min advance
        // should fail (the date-level check can't know about specific slot times)
        $this->assertFalse($result->canBook, 'Today date should be ineligible since midnight is before now + 60 min');
    }

    public function test_it_does_not_count_cancelled_bookings_toward_limit(): void
    {
        config()->set('venue-bookings.max_active_bookings_per_user', 1);

        // Create a cancelled booking (should not count)
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-04-01',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'cancelled',
        ]);

        $date = date('Y-m-d', strtotime('+7 days'));

        $result = $this->service->canUserBook($this->user->id, $this->resource->id, $date);

        $this->assertTrue($result->canBook);
        $this->assertEmpty($result->reasons);
    }
}
