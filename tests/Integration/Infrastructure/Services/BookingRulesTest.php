<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\VenueBookings\Domain\Exceptions\BookingLimitExceededException;
use Modules\VenueBookings\Domain\Exceptions\TooFarToBookException;
use Modules\VenueBookings\Domain\Exceptions\TooSoonToBookException;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Repositories\EloquentBookingRepository;
use Modules\VenueBookings\Infrastructure\Services\BookingRules;
use Modules\VenueBookings\Infrastructure\Services\BookingSettingsReader;
use Tests\TestCase;

final class BookingRulesTest extends TestCase
{
    use RefreshDatabase;

    private BookingRules $rules;

    private BookableResourceModel $resource;

    private UserModel $user;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('modules.settings.venue-bookings.timezone', 'Europe/Madrid');
        config()->set('modules.settings.venue-bookings.max_active_bookings_per_user', 2);
        config()->set('modules.settings.venue-bookings.min_advance_minutes', 60);
        config()->set('modules.settings.venue-bookings.max_future_days', 30);

        $this->rules = new BookingRules(new EloquentBookingRepository, new BookingSettingsReader);

        $this->resource = BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Room',
            'slug' => 'test-room',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $this->user = UserModel::factory()->create();
    }

    public function test_the_earliest_start_is_now_plus_the_minimum_advance_at_the_venue(): void
    {
        $this->travelTo(CarbonImmutable::create(2026, 3, 16, 9, 0, 0, 'Europe/Madrid'));

        $this->assertSame('2026-03-16 10:00', $this->rules->earliestStart()->format('Y-m-d H:i'));
        $this->assertSame('Europe/Madrid', $this->rules->earliestStart()->getTimezone()->getName());
    }

    public function test_a_start_before_the_earliest_start_is_too_soon_and_one_on_it_is_not(): void
    {
        $this->travelTo(CarbonImmutable::create(2026, 3, 16, 9, 0, 0, 'Europe/Madrid'));

        $this->assertTrue($this->rules->startsTooSoon('2026-03-16', '09:59'));
        $this->assertFalse($this->rules->startsTooSoon('2026-03-16', '10:00'));
        $this->assertTrue($this->rules->startsTooSoon('2026-03-15', '18:00'));
        $this->assertFalse($this->rules->startsTooSoon('2026-03-17', '08:00'));
    }

    public function test_the_minimum_advance_is_read_on_the_venue_clock(): void
    {
        // 09:30 in the Canaries is 10:30 in Madrid: with a Canary venue a 10:30 start is exactly one hour ahead
        config()->set('modules.settings.venue-bookings.timezone', 'Atlantic/Canary');
        $this->travelTo(CarbonImmutable::create(2026, 3, 16, 9, 30, 0, 'Atlantic/Canary'));

        $this->assertFalse($this->rules->startsTooSoon('2026-03-16', '10:30'));
        $this->assertTrue($this->rules->startsTooSoon('2026-03-16', '10:29'));
    }

    public function test_a_day_is_too_soon_only_when_all_of_it_is_before_the_earliest_start(): void
    {
        config()->set('modules.settings.venue-bookings.min_advance_minutes', 1440);
        $this->travelTo(CarbonImmutable::create(2026, 3, 16, 12, 0, 0, 'Europe/Madrid'));

        $this->assertTrue($this->rules->dayIsTooSoon('2026-03-16'));
        $this->assertFalse($this->rules->dayIsTooSoon('2026-03-17'));
    }

    public function test_the_last_bookable_day_is_counted_from_the_venue_day(): void
    {
        // 00:30 on 1 March in Madrid is still 28 February in UTC
        $this->travelTo(CarbonImmutable::create(2026, 3, 1, 0, 30, 0, 'Europe/Madrid'));

        $this->assertFalse($this->rules->isTooFar('2026-03-31'));
        $this->assertTrue($this->rules->isTooFar('2026-04-01'));
    }

    public function test_the_limit_counts_pending_and_confirmed_bookings_only(): void
    {
        $this->bookingOf('pending');
        $this->assertFalse($this->rules->hasReachedBookingLimit($this->user->id));

        $this->bookingOf('cancelled');
        $this->bookingOf('rejected');
        $this->assertFalse($this->rules->hasReachedBookingLimit($this->user->id));

        $this->bookingOf('confirmed');
        $this->assertTrue($this->rules->hasReachedBookingLimit($this->user->id));
    }

    public function test_assert_can_book_throws_the_limit_first(): void
    {
        $this->travelTo(CarbonImmutable::create(2026, 3, 16, 9, 0, 0, 'Europe/Madrid'));
        $this->bookingOf('pending');
        $this->bookingOf('confirmed');

        $this->expectException(BookingLimitExceededException::class);

        $this->rules->assertCanBook($this->user->id, '2026-03-16', '09:30');
    }

    public function test_assert_can_book_throws_too_soon(): void
    {
        $this->travelTo(CarbonImmutable::create(2026, 3, 16, 9, 0, 0, 'Europe/Madrid'));

        $this->expectException(TooSoonToBookException::class);

        $this->rules->assertCanBook($this->user->id, '2026-03-16', '09:30');
    }

    public function test_assert_can_book_throws_too_far(): void
    {
        $this->travelTo(CarbonImmutable::create(2026, 3, 1, 9, 0, 0, 'Europe/Madrid'));

        $this->expectException(TooFarToBookException::class);

        $this->rules->assertCanBook($this->user->id, '2026-04-01', '10:00');
    }

    public function test_assert_can_book_lets_a_valid_booking_through(): void
    {
        $this->travelTo(CarbonImmutable::create(2026, 3, 1, 9, 0, 0, 'Europe/Madrid'));

        $this->rules->assertCanBook($this->user->id, '2026-03-31', '10:00');

        $this->addToAssertionCount(1);
    }

    private function bookingOf(string $status): void
    {
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-04-01',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => $status,
        ]);
    }
}
