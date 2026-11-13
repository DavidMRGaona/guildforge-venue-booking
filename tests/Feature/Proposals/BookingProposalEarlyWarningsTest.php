<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Proposals;

use App\Application\Proposals\DTOs\ProposedEventDTO;
use App\Application\Proposals\Services\EventProposalRequestRegistryInterface;
use App\Application\Proposals\Services\EventProposalRequestServiceInterface;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Modules\VenueBookings\Application\Services\SlotAvailabilityServiceInterface;
use Modules\VenueBookings\Domain\Enums\BookingStatus;
use Modules\VenueBookings\Domain\ValueObjects\TimeRange;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Modules\VenueBookings\Infrastructure\Proposals\BookingProposalRequest;
use Modules\VenueBookings\Tests\Support\OpensResourceEveryDay;
use RuntimeException;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The early warnings of the space request (vb#140, core#612): they read the same rules apply()
 * uses, never reserve anything and never block the author.
 */
final class BookingProposalEarlyWarningsTest extends ModuleTestCase
{
    use OpensResourceEveryDay;

    private const string VENUE_TIMEZONE = 'Europe/Madrid';

    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    private BookableResourceModel $room;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('modules.settings.venue-bookings.timezone', self::VENUE_TIMEZONE);
        $this->travelTo(CarbonImmutable::create(2026, 11, 20, 9, 0, 0, self::VENUE_TIMEZONE));

        BookableResourceModel::query()->update(['status' => 'inactive']);
        $this->room = $this->resource('Sala grande');
        $this->openEveryDay($this->room->id, '08:00', '23:00', maxConsecutiveSlots: 10);
    }

    private function resource(string $name, string $status = 'active'): BookableResourceModel
    {
        return BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name),
            'status' => $status,
            'sort_order' => 0,
        ]);
    }

    private function request(): BookingProposalRequest
    {
        $request = app(EventProposalRequestRegistryInterface::class)->request(BookingProposalRequest::KEY);
        $this->assertInstanceOf(BookingProposalRequest::class, $request);

        return $request;
    }

    /**
     * @return list<string>
     */
    private function warningsFor(string $start, string $end, ?string $resourceId = null): array
    {
        return $this->request()->warnings(
            new ProposedEventDTO(
                CarbonImmutable::parse($start, self::VENUE_TIMEZONE)->toDateTimeImmutable(),
                CarbonImmutable::parse($end, self::VENUE_TIMEZONE)->toDateTimeImmutable(),
            ),
            ['resource' => $resourceId ?? $this->room->id, 'note' => null],
        );
    }

    private function book(string $date, string $start, string $end): void
    {
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->room->id,
            'user_id' => UserModel::factory()->create()->id,
            'date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'status' => BookingStatus::Confirmed,
        ]);
    }

    public function test_a_free_space_inside_its_slots_gives_no_warning(): void
    {
        $this->assertSame([], $this->warningsFor('2026-12-05 17:00', '2026-12-05 21:00'));
    }

    public function test_a_taken_slot_warns_naming_the_date(): void
    {
        $this->book('2026-12-05', '18:00', '19:00');

        $this->assertSame(
            ['Sala grande ya está ocupado el 05/12/2026 entre las 17:00 y las 21:00: elige otro espacio u otras fechas.'],
            $this->warningsFor('2026-12-05 17:00', '2026-12-05 21:00'),
        );
    }

    public function test_the_date_is_read_in_the_venue_timezone(): void
    {
        $this->book('2026-12-06', '00:00', '01:00');
        $this->room->update(['status' => 'active']);

        // 23:30 UTC on the 5th is 00:30 on the 6th in Madrid
        $warnings = $this->request()->warnings(
            new ProposedEventDTO(new \DateTimeImmutable('2026-12-05 23:30:00 UTC'), new \DateTimeImmutable('2026-12-06 00:45:00 UTC')),
            ['resource' => $this->room->id, 'note' => null],
        );

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('06/12/2026', $warnings[0]);
    }

    public function test_an_event_that_has_started_warns_naming_the_date_and_comes_before_the_schedule_and_slot_warnings(): void
    {
        // Now is 20/11 at 09:00; the event began an hour ago, on top of a taken slot
        $this->book('2026-11-20', '08:00', '09:00');
        $this->room->update(['status' => 'active']);

        $this->assertSame(
            ['El evento empezó el 20/11/2026 a las 08:00: quien revise tendrá que reservar a mano, si aún hace falta.'],
            $this->warningsFor('2026-11-20 08:00', '2026-11-20 10:00'),
        );
    }

    public function test_a_multi_day_event_that_has_started_gets_the_multi_day_warning_first(): void
    {
        $warnings = $this->warningsFor('2026-11-19 20:00', '2026-11-21 14:00');

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('cubre un solo día', $warnings[0]);
    }

    public function test_a_future_event_does_not_warn_about_starting(): void
    {
        $this->assertSame([], $this->warningsFor('2026-11-20 10:00', '2026-11-20 11:00'));
    }

    public function test_an_event_starting_exactly_now_has_started(): void
    {
        $warnings = $this->warningsFor('2026-11-20 09:00', '2026-11-20 11:00');

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('empezó el 20/11/2026 a las 09:00', $warnings[0]);
    }

    public function test_the_start_is_compared_by_the_minute_like_apply_does(): void
    {
        // 09:00:30 is booked as 09:00, which is already past at 09:00:10
        $this->travelTo(CarbonImmutable::create(2026, 11, 20, 9, 0, 10, self::VENUE_TIMEZONE));

        $warnings = $this->request()->warnings(
            new ProposedEventDTO(
                CarbonImmutable::parse('2026-11-20 09:00:30', self::VENUE_TIMEZONE)->toDateTimeImmutable(),
                CarbonImmutable::parse('2026-11-20 11:00', self::VENUE_TIMEZONE)->toDateTimeImmutable(),
            ),
            ['resource' => $this->room->id, 'note' => null],
        );

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('empezó', $warnings[0]);
    }

    public function test_a_multi_day_event_warns(): void
    {
        $this->assertSame(
            ['El evento va del 05/12/2026 al 07/12/2026 y la reserva de Sala grande cubre un solo día: quien revise tendrá que hacer las reservas a mano.'],
            $this->warningsFor('2026-12-05 17:00', '2026-12-07 14:00'),
        );
    }

    public function test_an_event_ending_at_midnight_the_next_day_counts_as_multi_day(): void
    {
        $warnings = $this->warningsFor('2026-12-05 20:00', '2026-12-06 00:00');

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('del 05/12/2026 al 06/12/2026', $warnings[0]);
    }

    public function test_hours_outside_the_opening_hours_warn(): void
    {
        $this->assertSame(
            ['El horario del evento (05/12/2026, de 06:00 a 09:00) no encaja con los turnos o el horario de Sala grande: quien revise tendrá que reservar a mano.'],
            $this->warningsFor('2026-12-05 06:00', '2026-12-05 09:00'),
        );
    }

    public function test_more_consecutive_slots_than_allowed_warn(): void
    {
        $other = $this->resource('Sala pequeña');
        $this->openEveryDay($other->id, '08:00', '23:00', maxConsecutiveSlots: 2);

        $warnings = $this->warningsFor('2026-12-05 17:00', '2026-12-05 21:00', $other->id);

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('no encaja con los turnos', $warnings[0]);
    }

    public function test_a_space_without_a_schedule_warns(): void
    {
        $bare = $this->resource('Trastero');

        $warnings = $this->warningsFor('2026-12-05 17:00', '2026-12-05 21:00', $bare->id);

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('no encaja con los turnos', $warnings[0]);
    }

    public function test_a_space_that_is_no_longer_active_warns(): void
    {
        $this->room->update(['status' => 'inactive']);

        $this->assertSame(
            ['Sala grande ya no admite reservas: elige otro espacio o desmarca la petición.'],
            $this->warningsFor('2026-12-05 17:00', '2026-12-05 21:00'),
        );
    }

    public function test_a_space_that_no_longer_exists_warns(): void
    {
        $this->assertSame(
            ['El espacio pedido ya no existe: elige otro o desmarca la petición.'],
            $this->warningsFor('2026-12-05 17:00', '2026-12-05 21:00', (string) Str::uuid()),
        );
    }

    public function test_warning_reserves_nothing(): void
    {
        $this->warningsFor('2026-12-05 17:00', '2026-12-05 21:00');

        $this->assertSame(0, BookingModel::query()->count());
    }

    public function test_the_core_shows_the_warning_for_a_stored_proposal(): void
    {
        $this->book('2026-12-05', '17:00', '18:00');
        $event = EventModel::factory()->proposal(ProposalStatus::Draft, UserModel::factory()->create())->create([
            'start_date' => CarbonImmutable::parse('2026-12-05 17:00', self::VENUE_TIMEZONE),
            'end_date' => CarbonImmutable::parse('2026-12-05 21:00', self::VENUE_TIMEZONE),
        ]);
        $service = app(EventProposalRequestServiceInterface::class);
        $service->saveRequested($event->id, [
            BookingProposalRequest::KEY => ['wanted' => '1', 'values' => ['resource' => $this->room->id, 'note' => null]],
        ]);

        $stored = $service->forEvent($event->id);

        $this->assertCount(1, $stored);
        $this->assertCount(1, $stored[0]->warnings);
        $this->assertStringContainsString('ya está ocupado el 05/12/2026', $stored[0]->warnings[0]);
    }

    public function test_a_failing_check_gives_no_warning_and_does_not_break_the_page(): void
    {
        $event = EventModel::factory()->proposal(ProposalStatus::Draft, UserModel::factory()->create())->create([
            'start_date' => CarbonImmutable::parse('2026-12-05 17:00', self::VENUE_TIMEZONE),
            'end_date' => CarbonImmutable::parse('2026-12-05 21:00', self::VENUE_TIMEZONE),
        ]);
        $service = app(EventProposalRequestServiceInterface::class);
        $service->saveRequested($event->id, [
            BookingProposalRequest::KEY => ['wanted' => '1', 'values' => ['resource' => $this->room->id, 'note' => null]],
        ]);

        $this->app->bind(SlotAvailabilityServiceInterface::class, fn () => new class implements SlotAvailabilityServiceInterface
        {
            public function getAvailableSlotsForDate(string $resourceId, string $date): array
            {
                return [];
            }

            public function isSlotAvailable(string $resourceId, string $date, TimeRange $timeRange): bool
            {
                throw new RuntimeException('The booking table is not reachable.');
            }
        });
        $this->app->forgetInstance(EventProposalRequestRegistryInterface::class);

        $stored = app(EventProposalRequestServiceInterface::class)->forEvent($event->id);

        $this->assertCount(1, $stored);
        $this->assertSame([], $stored[0]->warnings);
    }
}
