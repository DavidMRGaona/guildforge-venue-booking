<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Filament\Widgets;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\VenueBookings\Filament\Resources\BookingResource;
use Modules\VenueBookings\Filament\Widgets\TodayBookingsWidget;
use Modules\VenueBookings\Filament\Widgets\UpcomingBookingsWidget;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use ReflectionMethod;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The dashboard widgets judge "today" and "upcoming" by the venue's clock, and a row of the
 * upcoming bookings opens the booking for whoever may edit it, as the bookings list does.
 */
final class BookingWidgetsTest extends ModuleTestCase
{
    use CreatesUsersWithPermissions;
    use InteractsWithModulePanel;

    // Far from the application's timezone (Europe/Madrid), so the two days differ at night
    private const string VENUE_TIMEZONE = 'America/New_York';

    private const string VIEW_ANY = 'venuebookings:bookings.view_any';

    private const string MANAGE = 'venuebookings:bookings.manage';

    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    private BookableResourceModel $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([BookingResource::class]);
        config()->set('modules.settings.venue-bookings.timezone', self::VENUE_TIMEZONE);

        $this->room = BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sala grande',
            'slug' => 'sala-grande',
            'status' => 'active',
            'sort_order' => 0,
        ]);
    }

    public function test_a_row_opens_the_booking_for_whoever_may_edit_it(): void
    {
        $this->atVenueTime('2026-11-09 10:00');
        $booking = $this->booking('2026-11-09', '17:00', '19:00');

        $this->actingAs($this->editorWithPermissions([self::VIEW_ANY, self::MANAGE]));
        $this->assertSame(
            BookingResource::getUrl('edit', ['record' => $booking->id]),
            $this->upcomingRowUrl($booking),
        );

        // Listing bookings is not enough to edit them: the row is not a link
        $this->actingAs($this->editorWithPermissions([self::VIEW_ANY]));
        $this->assertNull($this->upcomingRowUrl($booking));
    }

    public function test_upcoming_bookings_are_the_ones_not_ended_by_the_venue_clock(): void
    {
        // 21:00 at the venue, already the next day (03:00) in the application's timezone
        $this->atVenueTime('2026-11-09 21:00');
        $ended = $this->booking('2026-11-09', '10:00', '12:00');
        $underWay = $this->booking('2026-11-09', '19:00', '23:00');
        $later = $this->booking('2026-11-09', '22:00', '23:30');
        $overnight = $this->booking('2026-11-09', '23:00', '01:00');
        $tomorrow = $this->booking('2026-11-10', '10:00', '12:00');
        $cancelled = $this->booking('2026-11-10', '12:00', '14:00', 'cancelled');

        $this->actingAs(UserModel::factory()->admin()->create());

        // The widget defers loading its table
        Livewire::test(UpcomingBookingsWidget::class)
            ->call('loadTable')
            ->assertCanSeeTableRecords([$underWay, $later, $overnight, $tomorrow])
            ->assertCanNotSeeTableRecords([$ended, $cancelled]);
    }

    public function test_an_overnight_booking_from_yesterday_is_upcoming_until_it_ends(): void
    {
        $this->atVenueTime('2026-11-10 00:30');
        $stillOn = $this->booking('2026-11-09', '23:00', '01:00');
        $over = $this->booking('2026-11-09', '22:00', '00:00');

        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(UpcomingBookingsWidget::class)
            ->call('loadTable')
            ->assertCanSeeTableRecords([$stillOn])
            ->assertCanNotSeeTableRecords([$over]);
    }

    public function test_today_is_the_venue_day(): void
    {
        // 21:00 on the 9th at the venue, already the 10th in the application's timezone
        $this->atVenueTime('2026-11-09 21:00');
        $this->booking('2026-11-09', '19:00', '23:00');

        $this->actingAs(UserModel::factory()->admin()->create());

        $this->assertSame('1', $this->todayStat(0)->getValue());
    }

    private function atVenueTime(string $wallClock): void
    {
        $this->travelTo(CarbonImmutable::parse($wallClock, self::VENUE_TIMEZONE));
    }

    private function booking(string $date, string $startTime, string $endTime, string $status = 'confirmed'): BookingModel
    {
        return BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->room->id,
            'user_id' => UserModel::factory()->create()->id,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => $status,
        ]);
    }

    private function upcomingRowUrl(BookingModel $booking): ?string
    {
        /** @var UpcomingBookingsWidget $widget */
        $widget = Livewire::test(UpcomingBookingsWidget::class)->instance();

        return $widget->getTable()->getRecordUrl($booking);
    }

    private function todayStat(int $index): Stat
    {
        $widget = Livewire::test(TodayBookingsWidget::class)->instance();
        $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

        /** @var array<Stat> $stats */
        return $stats[$index];
    }
}
