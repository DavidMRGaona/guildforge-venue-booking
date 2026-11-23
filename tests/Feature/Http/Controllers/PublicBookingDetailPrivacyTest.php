<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Http\Controllers;

use App\Application\Activity\DTOs\ActivityDescriptionsResultDTO;
use App\Application\Activity\DTOs\ActivitySubjectDTO;
use App\Application\Activity\Services\ActivityReaderInterface;
use App\Application\Linking\Services\LinkableRecordLookupInterface;
use App\Application\Modules\Services\ModuleContextServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\VenueBookings\Application\DTOs\Response\BookingResponseDTO;
use Modules\VenueBookings\Domain\Repositories\BookingRepositoryInterface;
use Modules\VenueBookings\Domain\ValueObjects\BookingId;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The booking detail is a public endpoint (the calendar hands out the id), so what it names is
 * decided on the server, on the raw JSON: the game table or campaign only when its association
 * setting lets the viewer see it and the module that owns it says the viewer may (never a draft
 * or private one), and the cancellation reason only for whoever booked and for bookings.manage.
 */
final class PublicBookingDetailPrivacyTest extends ModuleTestCase
{
    use CreatesUsersWithPermissions;

    private const string MANAGE = 'venuebookings:bookings.manage';

    private const string TABLE_TITLE = 'La tumba de los horrores';

    private const string CAMPAIGN_TITLE = 'La maldición de Strahd, martes';

    private const string REASON = 'Se cae la reunión de la junta, perdón';

    private const string TABLE_ID = '0199f0a0-0000-7000-8000-000000000001';

    private const string CAMPAIGN_ID = '0199f0a0-0000-7000-8000-000000000002';

    private const string DRAFT_TABLE_ID = '0199f0a0-0000-7000-8000-000000000003';

    private const string DRAFT_CAMPAIGN_ID = '0199f0a0-0000-7000-8000-000000000004';

    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    private BookableResourceModel $room;

    private UserModel $booker;

    /** @var list<string> */
    private array $describedTypes = [];

    protected function setUp(): void
    {
        parent::setUp();

        // The game-tables module is optional and may not be installed next to this one
        $modules = $this->createStub(ModuleContextServiceInterface::class);
        $modules->method('isEnabled')->willReturnCallback(
            fn (string $name): bool => in_array($name, ['venue-bookings', 'game-tables'], true),
        );
        $this->app->instance(ModuleContextServiceInterface::class, $modules);

        // What the game-tables module tells about its published tables and campaigns, as the core's
        // activity reader hands it over; a draft is never described
        $this->app->instance(ActivityReaderInterface::class, $this->readerDescribing([
            'game-tables.session' => [self::TABLE_ID => self::TABLE_TITLE],
            'game-tables.campaign' => [self::CAMPAIGN_ID => self::CAMPAIGN_TITLE],
        ]));

        foreach (['game_tables', 'campaigns'] as $association) {
            config()->set("modules.settings.venue-bookings.associations.{$association}_enabled", true);
            config()->set("modules.settings.venue-bookings.associations.{$association}_visibility", 'public');
        }

        $this->room = BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sala grande',
            'slug' => 'sala-grande',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $this->booker = UserModel::factory()->create(['name' => 'Persona Reservante']);
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function visibilityByViewer(): iterable
    {
        yield 'public, guest' => ['public', 'guest', true];
        yield 'public, member' => ['public', 'member', true];
        yield 'authenticated, guest' => ['authenticated', 'guest', false];
        yield 'authenticated, member' => ['authenticated', 'member', true];
        yield 'authenticated, owner' => ['authenticated', 'owner', true];
        yield 'permission, guest' => ['permission', 'guest', false];
        yield 'permission, member' => ['permission', 'member', false];
        yield 'permission, owner' => ['permission', 'owner', false];
        yield 'permission, manager' => ['permission', 'manager', true];
        yield 'admin_only, guest' => ['admin_only', 'guest', false];
        yield 'admin_only, member' => ['admin_only', 'member', false];
        yield 'admin_only, owner' => ['admin_only', 'owner', false];
        yield 'admin_only, manager' => ['admin_only', 'manager', false];
        yield 'admin_only, admin' => ['admin_only', 'admin', true];
    }

    #[DataProvider('visibilityByViewer')]
    public function test_the_game_table_and_campaign_follow_the_visibility_setting(string $visibility, string $viewer, bool $named): void
    {
        config()->set('modules.settings.venue-bookings.associations.game_tables_visibility', $visibility);
        config()->set('modules.settings.venue-bookings.associations.campaigns_visibility', $visibility);
        $booking = $this->booking(['game_table_id' => self::TABLE_ID, 'campaign_id' => self::CAMPAIGN_ID]);
        $this->actAs($viewer);

        $response = $this->detail($booking)->assertOk();

        if ($named) {
            $response->assertJsonPath('game_table_name', self::TABLE_TITLE)
                ->assertJsonPath('campaign_name', self::CAMPAIGN_TITLE);

            return;
        }

        $response->assertJsonMissingPath('game_table_name')->assertJsonMissingPath('campaign_name');
        $this->assertStringNotContainsString(self::TABLE_TITLE, (string) $response->getContent());
        $this->assertStringNotContainsString(self::CAMPAIGN_TITLE, (string) $response->getContent());
    }

    public function test_the_visibility_of_the_game_table_and_the_campaign_are_independent(): void
    {
        config()->set('modules.settings.venue-bookings.associations.game_tables_visibility', 'admin_only');
        config()->set('modules.settings.venue-bookings.associations.campaigns_visibility', 'public');

        $response = $this->detail($this->booking(['game_table_id' => self::TABLE_ID, 'campaign_id' => self::CAMPAIGN_ID]))->assertOk();

        $response->assertJsonMissingPath('game_table_name')->assertJsonPath('campaign_name', self::CAMPAIGN_TITLE);
    }

    #[DataProvider('everyViewer')]
    public function test_a_draft_or_private_game_table_or_campaign_is_never_named(string $viewer): void
    {
        $booking = $this->booking(['game_table_id' => self::DRAFT_TABLE_ID, 'campaign_id' => self::DRAFT_CAMPAIGN_ID]);
        $this->actAs($viewer);

        $response = $this->detail($booking)->assertOk();

        $response->assertJsonMissingPath('game_table_name')->assertJsonMissingPath('campaign_name');
    }

    public function test_a_table_whose_id_is_made_up_is_not_named_either(): void
    {
        $response = $this->detail($this->booking(['game_table_id' => (string) Str::uuid()]))->assertOk();

        $response->assertJsonMissingPath('game_table_name');
    }

    public function test_nothing_is_named_while_the_association_is_off(): void
    {
        config()->set('modules.settings.venue-bookings.associations.game_tables_enabled', false);
        config()->set('modules.settings.venue-bookings.associations.campaigns_enabled', false);

        $response = $this->detail($this->booking(['game_table_id' => self::TABLE_ID, 'campaign_id' => self::CAMPAIGN_ID]))->assertOk();

        $response->assertJsonMissingPath('game_table_name')->assertJsonMissingPath('campaign_name');
    }

    public function test_the_module_that_owns_the_table_is_asked_once_per_kind_and_only_for_what_is_shown(): void
    {
        config()->set('modules.settings.venue-bookings.associations.game_tables_visibility', 'admin_only');

        $this->detail($this->booking(['game_table_id' => self::TABLE_ID, 'campaign_id' => self::CAMPAIGN_ID]))->assertOk();

        $this->assertSame(['game-tables.campaign'], $this->describedTypes);
    }

    /**
     * Deciding which fields the viewer sees needs only the settings: the game tables and
     * campaigns a booking form would offer are never listed for a detail.
     */
    #[DataProvider('everyViewer')]
    public function test_the_detail_never_lists_the_tables_or_campaigns_a_form_would_offer(string $viewer): void
    {
        // Listing goes through the core's linkable record lookup: the detail never asks it
        $lookup = $this->createMock(LinkableRecordLookupInterface::class);
        $lookup->expects($this->never())->method('search');
        $lookup->expects($this->never())->method('organizedBy');
        $this->app->instance(LinkableRecordLookupInterface::class, $lookup);
        $this->actAs($viewer);

        $this->detail($this->booking(['game_table_id' => self::TABLE_ID, 'campaign_id' => self::CAMPAIGN_ID]))
            ->assertOk()
            ->assertJsonPath('game_table_name', self::TABLE_TITLE)
            ->assertJsonPath('campaign_name', self::CAMPAIGN_TITLE);

    }

    public function test_the_viewer_is_the_one_the_module_is_asked_about(): void
    {
        $viewer = UserModel::factory()->create();
        $asked = [];
        $reader = $this->createStub(ActivityReaderInterface::class);
        $reader->method('describe')->willReturnCallback(
            function (string $type, array $ids, ?Authenticatable $who) use (&$asked): ActivityDescriptionsResultDTO {
                $asked[] = $who?->getAuthIdentifier();

                return ActivityDescriptionsResultDTO::read([]);
            },
        );
        $this->app->instance(ActivityReaderInterface::class, $reader);

        $this->actingAs($viewer);
        $this->detail($this->booking(['game_table_id' => self::TABLE_ID]))->assertOk();
        auth()->logout();
        $this->detail($this->booking(['game_table_id' => self::TABLE_ID]))->assertOk();

        $this->assertSame([$viewer->id, null], $asked);
    }

    public function test_a_game_table_the_module_cannot_describe_is_not_named(): void
    {
        $reader = $this->createStub(ActivityReaderInterface::class);
        $reader->method('describe')->willReturn(ActivityDescriptionsResultDTO::failed());
        $this->app->instance(ActivityReaderInterface::class, $reader);

        $response = $this->detail($this->booking(['game_table_id' => self::TABLE_ID, 'campaign_id' => self::CAMPAIGN_ID]))->assertOk();

        $response->assertJsonMissingPath('game_table_name')->assertJsonMissingPath('campaign_name');
    }

    #[DataProvider('everyViewer')]
    public function test_nothing_is_named_and_the_module_is_not_asked_while_game_tables_is_disabled(string $viewer): void
    {
        $modules = $this->createStub(ModuleContextServiceInterface::class);
        $modules->method('isEnabled')->willReturnCallback(fn (string $name): bool => $name === 'venue-bookings');
        $this->app->instance(ModuleContextServiceInterface::class, $modules);

        $booking = $this->booking(['game_table_id' => self::TABLE_ID, 'campaign_id' => self::CAMPAIGN_ID]);
        $this->actAs($viewer);

        $response = $this->detail($booking)->assertOk();

        $response->assertJsonMissingPath('game_table_name')->assertJsonMissingPath('campaign_name');
        $this->assertSame([], $this->describedTypes);
    }

    public function test_a_reader_that_throws_gives_no_name_and_does_not_break_the_detail(): void
    {
        $reader = $this->createStub(ActivityReaderInterface::class);
        $reader->method('describe')->willThrowException(new \RuntimeException('Reader unavailable'));
        $this->app->instance(ActivityReaderInterface::class, $reader);

        $response = $this->detail($this->booking(['game_table_id' => self::TABLE_ID, 'campaign_id' => self::CAMPAIGN_ID]))->assertOk();

        $response->assertJsonMissingPath('game_table_name')->assertJsonMissingPath('campaign_name');
        $this->assertStringNotContainsString('Reader unavailable', (string) $response->getContent());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function everyViewer(): iterable
    {
        yield 'guest' => ['guest'];
        yield 'member' => ['member'];
        yield 'owner' => ['owner'];
        yield 'manager' => ['manager'];
        yield 'admin' => ['admin'];
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function reasonByViewer(): iterable
    {
        yield 'guest' => ['guest', false];
        yield 'another member' => ['member', false];
        yield 'who booked' => ['owner', true];
        yield 'bookings.manage' => ['manager', true];
        yield 'administrator' => ['admin', true];
        yield 'bookings.view_any only' => ['viewer', false];
    }

    #[DataProvider('reasonByViewer')]
    public function test_the_cancellation_reason_is_only_for_who_booked_and_the_booking_team(string $viewer, bool $seen): void
    {
        $booking = $this->booking(['status' => 'cancelled', 'cancellation_reason' => self::REASON]);
        $this->actAs($viewer);

        $response = $this->detail($booking)->assertOk()->assertJsonPath('status', 'cancelled');

        if ($seen) {
            $response->assertJsonPath('cancellation_reason', self::REASON);

            return;
        }

        $response->assertJsonMissingPath('cancellation_reason');
        $this->assertStringNotContainsString(self::REASON, (string) $response->getContent());
    }

    public function test_a_rejection_reason_follows_the_same_rule(): void
    {
        $booking = $this->booking(['status' => 'rejected', 'cancellation_reason' => self::REASON]);

        $this->assertStringNotContainsString(self::REASON, (string) $this->detail($booking)->assertOk()->getContent());

        $this->actingAs($this->booker);
        $this->detail($booking)->assertOk()->assertJsonPath('cancellation_reason', self::REASON);
    }

    public function test_the_raw_answer_to_a_guest_carries_nothing_about_who_booked_or_the_notes(): void
    {
        $booking = $this->booking([
            'game_table_id' => self::TABLE_ID,
            'status' => 'cancelled',
            'cancellation_reason' => self::REASON,
            'admin_notes' => 'Llaves en conserjería',
        ]);
        $body = (string) $this->detail($booking)->assertOk()->getContent();

        foreach (['user_id', 'user_name', 'admin_notes', 'cancellation_reason'] as $key) {
            $this->assertStringNotContainsString('"'.$key.'"', $body);
        }
        foreach ([$this->booker->id, 'Persona Reservante', 'Llaves en conserjería', self::REASON] as $value) {
            $this->assertStringNotContainsString($value, $body);
        }
    }

    public function test_the_booking_dto_keeps_who_booked_and_the_notes_out_of_its_plain_array(): void
    {
        $dto = BookingResponseDTO::fromEntity(
            $this->bookings()->findOrFail(BookingId::fromString($this->booking([
                'status' => 'cancelled',
                'cancellation_reason' => self::REASON,
                'admin_notes' => 'Llaves en conserjería',
            ])->id)),
            'Sala grande',
            'Persona Reservante',
        );

        $plain = $dto->toArray();

        foreach (['user_id', 'user_name', 'admin_notes', 'cancellation_reason'] as $key) {
            $this->assertArrayNotHasKey($key, $plain);
        }
        $body = (string) json_encode($plain);
        foreach ([$this->booker->id, 'Persona Reservante', 'Llaves en conserjería', self::REASON] as $value) {
            $this->assertStringNotContainsString($value, $body);
        }
    }

    public function test_the_booking_dto_gives_the_team_who_booked_the_notes_and_the_reason_apart(): void
    {
        $dto = BookingResponseDTO::fromEntity(
            $this->bookings()->findOrFail(BookingId::fromString($this->booking([
                'status' => 'cancelled',
                'cancellation_reason' => self::REASON,
                'admin_notes' => 'Llaves en conserjería',
            ])->id)),
            'Sala grande',
            'Persona Reservante',
        );

        $staff = $dto->toStaffArray();

        $this->assertSame($this->booker->id, $staff['user_id']);
        $this->assertSame('Persona Reservante', $staff['user_name']);
        $this->assertSame('Llaves en conserjería', $staff['admin_notes']);
        $this->assertSame(self::REASON, $staff['cancellation_reason']);
        $this->assertSame($dto->id, $staff['id']);
    }

    private function bookings(): BookingRepositoryInterface
    {
        return $this->app->make(BookingRepositoryInterface::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function booking(array $attributes = []): BookingModel
    {
        return BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->room->id,
            'user_id' => $this->booker->id,
            'date' => now()->addWeek()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'confirmed',
            ...$attributes,
        ]);
    }

    private function actAs(string $viewer): void
    {
        match ($viewer) {
            'guest' => null,
            'member' => $this->actingAs(UserModel::factory()->create()),
            'owner' => $this->actingAs($this->booker),
            'manager' => $this->actingAs($this->editorWithPermissions([self::MANAGE])),
            'viewer' => $this->actingAs($this->editorWithPermissions(['venuebookings:bookings.view_any'])),
            'admin' => $this->actingAs(UserModel::factory()->admin()->create()),
            default => throw new \InvalidArgumentException("Unknown viewer {$viewer}"),
        };
    }

    /**
     * @return TestResponse<Response>
     */
    private function detail(BookingModel $booking): TestResponse
    {
        return $this->getJson(route('bookings.api.show', $booking->id));
    }

    /**
     * @param  array<string, array<string, string>>  $titlesByType  type => subject id => title
     */
    private function readerDescribing(array $titlesByType): ActivityReaderInterface
    {
        $reader = $this->createStub(ActivityReaderInterface::class);
        $reader->method('describe')->willReturnCallback(
            function (string $type, array $ids) use ($titlesByType): ActivityDescriptionsResultDTO {
                $this->describedTypes[] = $type;

                $subjects = [];
                foreach ($ids as $id) {
                    $title = $titlesByType[$type][$id] ?? null;

                    if ($title !== null) {
                        $subjects[] = new ActivitySubjectDTO($id, $title);
                    }
                }

                return ActivityDescriptionsResultDTO::read($subjects);
            },
        );

        return $reader;
    }
}
