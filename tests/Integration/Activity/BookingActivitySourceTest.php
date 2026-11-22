<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Integration\Activity;

use App\Application\Activity\Contracts\ActivitySourceInterface;
use App\Application\Activity\Contracts\OffersActivityAttributes;
use App\Application\Activity\DTOs\ActivityFactDTO;
use App\Application\Activity\DTOs\ActivitySubjectFactsDTO;
use App\Domain\Activity\ActivityKind;
use App\Domain\Activity\ActivityRole;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\VenueBookings\Filament\Resources\BookingResource;
use Modules\VenueBookings\Infrastructure\Activity\BookingActivitySource;
use Modules\VenueBookings\Tests\Support\MakesBookings;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Activity\ActivitySourceTestCase;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Modules\InteractsWithModulePanel;

/**
 * «Reservas del local» as an activity source (core#774): every completed booking, with the person
 * who booked as a `booker` fact at the moment the booking ended. Who booked is not shown to other
 * members on the site, so the achievements module only ever builds totals from it.
 */
final class BookingActivitySourceTest extends ActivitySourceTestCase
{
    use CreatesUsersWithPermissions;
    use InteractsWithModulePanel;
    use MakesBookings;

    protected ?string $moduleName = 'venue-bookings';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRoom();
    }

    protected function activitySource(): ActivitySourceInterface
    {
        return $this->app->make(BookingActivitySource::class);
    }

    protected function createCountedActivitySubject(): string
    {
        return $this->makeBooking()->id;
    }

    public function test_the_source_is_about_venue_bookings_and_offers_no_attributes(): void
    {
        $source = $this->activitySource();

        $this->assertSame('venue-bookings.booking', $source->type());
        $this->assertSame(ActivityKind::VenueBooking, $source->kind());
        $this->assertNotSame('', $source->label());
        $this->assertNotInstanceOf(OffersActivityAttributes::class, $source);
    }

    public function test_a_completed_booking_is_one_booker_fact_at_the_moment_it_ended(): void
    {
        $user = UserModel::factory()->create();
        $booking = $this->makeBooking(user: $user, date: '2026-11-10', start: '18:00', end: '20:00');

        $facts = $this->activitySource()->facts($booking->id);

        $this->assertCount(1, $facts);
        $this->assertSame($user->id, $facts[0]->userId);
        $this->assertSame(ActivityRole::Booker, $facts[0]->role);
        $this->assertSame([], $facts[0]->attributes);
        $this->assertNull($facts[0]->placement);
        // 20:00 in Madrid in November is 19:00 UTC
        $this->assertSame($this->instant('2026-11-10 19:00:00', 'UTC'), $facts[0]->occurredAt->getTimestamp());
    }

    public function test_a_booking_that_crosses_midnight_ends_on_the_next_day(): void
    {
        $booking = $this->makeBooking(date: '2026-11-10', start: '22:00', end: '02:00');

        $facts = $this->activitySource()->facts($booking->id);

        // 02:00 on the 11th in Madrid is 01:00 UTC
        $this->assertSame($this->instant('2026-11-11 01:00:00', 'UTC'), $facts[0]->occurredAt->getTimestamp());
    }

    public function test_the_end_is_read_in_the_venue_timezone_not_in_the_display_one(): void
    {
        config()->set('modules.settings.venue-bookings.timezone', 'Asia/Tokyo');
        $booking = $this->makeBooking(date: '2026-11-10', start: '07:00', end: '09:00');

        $facts = $this->activitySource()->facts($booking->id);

        // 09:00 in Tokyo (UTC+9) is midnight UTC
        $this->assertSame($this->instant('2026-11-10 00:00:00', 'UTC'), $facts[0]->occurredAt->getTimestamp());
    }

    public function test_the_end_of_a_booking_on_the_night_the_clocks_go_back_is_the_real_instant(): void
    {
        // Madrid goes back from 03:00 to 02:00 on Sunday 25 October 2026: 03:00 that morning is already UTC+1
        $booking = $this->makeBooking(date: '2026-10-24', start: '23:00', end: '03:00');

        $facts = $this->activitySource()->facts($booking->id);

        $this->assertSame($this->instant('2026-10-25 02:00:00', 'UTC'), $facts[0]->occurredAt->getTimestamp());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function statusesThatNeverCount(): array
    {
        return [
            'pending' => ['pending'],
            // Confirmed is still not completed, even once it is over: staff may mark it a no-show
            'confirmed' => ['confirmed'],
            'cancelled' => ['cancelled'],
            'no show' => ['no_show'],
            'rejected' => ['rejected'],
            'expired' => ['expired'],
        ];
    }

    #[DataProvider('statusesThatNeverCount')]
    public function test_only_completed_bookings_count(string $status): void
    {
        $booking = $this->makeBooking($status, date: '2020-01-01');

        $this->assertSame([], $this->activitySource()->facts($booking->id));
        $this->assertSame([], $this->history());
    }

    public function test_an_id_that_is_not_a_uuid_has_no_facts_and_an_upper_case_one_finds_its_booking(): void
    {
        $booking = $this->makeBooking();

        $this->assertSame([], $this->activitySource()->facts('not-an-id'));
        $this->assertSame([], $this->activitySource()->facts(''));
        $this->assertCount(1, $this->activitySource()->facts(strtoupper($booking->id)));
    }

    public function test_the_answer_is_the_same_for_a_guest_and_for_any_signed_in_account(): void
    {
        $this->makeBooking();
        $this->makeBooking(date: '2026-11-11');
        $source = $this->activitySource();
        $id = $this->history()[0]->subjectId;

        $asGuest = [$this->comparable($source->facts($id)), $this->history()];

        $this->actingAs(UserModel::factory()->create());
        $asMember = [$this->comparable($source->facts($id)), $this->history()];

        $this->actingAs($this->editorWithPermissions(['venuebookings:bookings.view_any', 'venuebookings:bookings.manage']));
        $asStaff = [$this->comparable($source->facts($id)), $this->history()];

        $this->assertEquals($asGuest, $asMember);
        $this->assertEquals($asGuest, $asStaff);
        $this->assertCount(2, $asGuest[1]);
    }

    public function test_a_deactivated_account_keeps_its_fact(): void
    {
        $user = UserModel::factory()->create();
        $booking = $this->makeBooking(user: $user);

        $user->delete();

        $this->assertCount(1, $this->activitySource()->facts($booking->id));
    }

    public function test_history_pages_by_cursor_and_lists_every_completed_booking_once(): void
    {
        $counted = [];
        foreach (range(1, 5) as $day) {
            $counted[] = $this->makeBooking(date: sprintf('2026-11-%02d', $day))->id;
        }
        $this->makeBooking('cancelled');
        $this->makeBooking('confirmed');
        $source = $this->activitySource();

        $first = $source->history(null, 2);
        $second = $source->history($first->nextCursor, 2);
        $third = $source->history($second->nextCursor, 2);

        $this->assertCount(2, $first->subjects);
        $this->assertCount(2, $second->subjects);
        $this->assertCount(1, $third->subjects);
        $this->assertNotNull($first->nextCursor);
        $this->assertNotNull($second->nextCursor);
        $this->assertNull($third->nextCursor);

        $ids = array_map(static fn ($subject): string => $subject->subjectId, [...$first->subjects, ...$second->subjects, ...$third->subjects]);
        $this->assertEqualsCanonicalizing($counted, $ids);
        $this->assertSame($ids, array_values(array_unique($ids)));
        // A stable order, so a walk never repeats or skips
        $sorted = $ids;
        sort($sorted);
        $this->assertSame($sorted, $ids);
    }

    public function test_a_page_that_fills_the_limit_exactly_is_the_last_one(): void
    {
        $this->makeBooking();
        $this->makeBooking(date: '2026-11-11');

        $page = $this->activitySource()->history(null, 2);

        $this->assertCount(2, $page->subjects);
        $this->assertNull($page->nextCursor);
    }

    public function test_history_never_does_more_work_than_the_core_would_ask_for(): void
    {
        $this->makeBooking();

        // The core never asks for more than 500, so a source does not trust a larger limit either
        $page = $this->activitySource()->history(null, 1_000_000);

        $this->assertCount(1, $page->subjects);
    }

    public function test_history_reads_one_page_with_a_fixed_number_of_queries(): void
    {
        foreach (range(1, 6) as $day) {
            $this->makeBooking(date: sprintf('2026-11-%02d', $day));
        }
        $source = $this->activitySource();
        $source->history(null, 1);
        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });

        $source->history(null, 5);

        // The facts come with the page: no query per booking
        $this->assertLessThanOrEqual(2, $queries);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function cursorsThatCannotBeRead(): array
    {
        return [
            'not base64url' => ['***'],
            'another prefix' => [rtrim(strtr(base64_encode('gt1|'.'0198d1b3-0000-7000-8000-000000000000'), '+/', '-_'), '=')],
            'not an id' => [rtrim(strtr(base64_encode('vb1|nope'), '+/', '-_'), '=')],
            'empty' => [''],
        ];
    }

    #[DataProvider('cursorsThatCannotBeRead')]
    public function test_a_cursor_that_cannot_be_read_throws(string $cursor): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->activitySource()->history($cursor, 10);
    }

    public function test_a_limit_below_one_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->activitySource()->history(null, 0);
    }

    public function test_a_guest_is_described_nothing(): void
    {
        $booking = $this->makeBooking();

        $this->assertSame([], $this->activitySource()->describe([$booking->id], null));
    }

    public function test_the_owner_sees_their_own_booking_described_with_the_link_to_their_bookings(): void
    {
        $owner = UserModel::factory()->create(['name' => 'Persona Reservante']);
        $booking = $this->makeBooking(user: $owner, date: '2026-11-10');

        $described = $this->activitySource()->describe([$booking->id], $owner);

        $this->assertSame([$booking->id], array_keys($described));
        $this->assertSame($booking->id, $described[$booking->id]->subjectId);
        $this->assertStringContainsString('Sala grande', $described[$booking->id]->title);
        $this->assertStringContainsString('10/11/2026', $described[$booking->id]->title);
        $this->assertSame('/reservas/mis-reservas', $described[$booking->id]->url);
    }

    public function test_another_member_is_not_described_a_booking_that_is_not_theirs(): void
    {
        $booking = $this->makeBooking();

        $this->assertSame([], $this->activitySource()->describe([$booking->id], UserModel::factory()->create()));
    }

    public function test_whoever_may_see_every_booking_in_the_panel_is_described_all_with_the_panel_link(): void
    {
        $this->registerOnAdminPanel([BookingResource::class]);
        $first = $this->makeBooking();
        $second = $this->makeBooking(date: '2026-11-11');
        $staff = $this->editorWithPermissions(['venuebookings:bookings.view_any']);

        $described = $this->activitySource()->describe([$first->id, $second->id], $staff);

        $this->assertEqualsCanonicalizing([$first->id, $second->id], array_keys($described));
        $url = $described[$first->id]->url;
        $this->assertIsString($url);
        $this->assertStringStartsWith('/admin/', $url);
        $this->assertStringContainsString($first->id, $url);
    }

    public function test_a_description_never_names_the_person_who_booked(): void
    {
        $owner = UserModel::factory()->create(['name' => 'Nombre Secreto', 'display_name' => 'Apodo Secreto']);
        $booking = $this->makeBooking(user: $owner);
        $staff = $this->editorWithPermissions(['venuebookings:bookings.view_any']);

        $title = $this->activitySource()->describe([$booking->id], $staff)[$booking->id]->title;

        $this->assertStringNotContainsString('Secreto', $title);
    }

    public function test_describe_answers_only_for_valid_ids_asked(): void
    {
        $booking = $this->makeBooking();
        $other = $this->makeBooking(date: '2026-11-11');
        $staff = $this->editorWithPermissions(['venuebookings:bookings.view_any']);

        $described = $this->activitySource()->describe([strtoupper($booking->id), 'not-an-id', '', $this->unknownActivitySubjectId()], $staff);

        $this->assertSame([$booking->id], array_keys($described));
        $this->assertArrayNotHasKey($other->id, $described);
        $this->assertSame([], $this->activitySource()->describe([], $staff));
    }

    /**
     * @return list<ActivitySubjectFactsDTO>
     */
    private function history(): array
    {
        $subjects = [];
        $cursor = null;

        do {
            $page = $this->activitySource()->history($cursor, 100);
            $subjects = [...$subjects, ...$page->subjects];
            $cursor = $page->nextCursor;
        } while ($cursor !== null);

        return $subjects;
    }

    /**
     * @param  list<ActivityFactDTO>  $facts
     * @return list<string>
     */
    private function comparable(array $facts): array
    {
        return array_map(static fn (ActivityFactDTO $fact): string => json_encode([$fact->userId, $fact->role->value, $fact->occurredAt->getTimestamp()], JSON_THROW_ON_ERROR), $facts);
    }

    private function instant(string $time, string $zone): int
    {
        return (new DateTimeImmutable($time, new DateTimeZone($zone)))->getTimestamp();
    }
}
