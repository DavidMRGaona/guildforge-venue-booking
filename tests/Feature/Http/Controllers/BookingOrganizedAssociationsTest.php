<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Http\Controllers;

use App\Application\Impersonation\Services\ImpersonationServiceInterface;
use App\Application\Linking\DTOs\LinkableRecordDTO;
use App\Application\Linking\Services\LinkableRecordLookupInterface;
use App\Application\Modules\Services\ModuleContextServiceInterface;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Modules\VenueBookings\Tests\Support\OpensResourceEveryDay;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\Modules\ModuleTestCase;

/**
 * On the website a booking is linked only to a game table or campaign its booker organizes, as the
 * core's linkable record lookup tells (game-tables answers it): checked again when saving, never
 * the table of somebody else, even one anybody may see.
 */
final class BookingOrganizedAssociationsTest extends ModuleTestCase
{
    use OpensResourceEveryDay;

    private const string VENUE_TIMEZONE = 'Europe/Madrid';

    private const string OWN_TABLE = '0199f0a0-0000-7000-8000-000000000011';

    private const string OTHERS_TABLE = '0199f0a0-0000-7000-8000-000000000012';

    private const string OWN_CAMPAIGN = '0199f0a0-0000-7000-8000-000000000021';

    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    private BookableResourceModel $room;

    private UserModel $booker;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        app('router')->getRoutes()->refreshNameLookups();

        config()->set('modules.settings.venue-bookings.timezone', self::VENUE_TIMEZONE);
        $this->travelTo(CarbonImmutable::create(2026, 11, 10, 12, 0, 0, self::VENUE_TIMEZONE));
        config()->set('modules.settings.venue-bookings.min_advance_minutes', 0);
        config()->set('modules.settings.venue-bookings.max_future_days', 30);
        config()->set('modules.settings.venue-bookings.max_active_bookings_per_user', 5);

        foreach (['game_tables', 'campaigns'] as $association) {
            config()->set("modules.settings.venue-bookings.associations.{$association}_enabled", true);
            config()->set("modules.settings.venue-bookings.associations.{$association}_visibility", 'authenticated');
            config()->set("modules.settings.venue-bookings.associations.{$association}_required", false);
        }

        // The game-tables module is optional and may not be installed next to this one
        $modules = $this->createStub(ModuleContextServiceInterface::class);
        $modules->method('isEnabled')->willReturnCallback(
            fn (string $name): bool => in_array($name, ['venue-bookings', 'game-tables'], true),
        );
        $this->app->instance(ModuleContextServiceInterface::class, $modules);

        $this->booker = UserModel::factory()->create();
        $this->app->instance(LinkableRecordLookupInterface::class, $this->lookupOrganizedBy($this->booker));

        $this->room = BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sala grande',
            'slug' => 'sala-grande',
            'status' => 'active',
            'sort_order' => 0,
        ]);
        $this->openEveryDay($this->room->id, '10:00', '22:00');
    }

    public function test_a_booking_is_linked_to_a_table_and_a_campaign_its_booker_organizes(): void
    {
        $this->actingAs($this->booker);

        $this->book(['game_table_id' => self::OWN_TABLE, 'campaign_id' => self::OWN_CAMPAIGN])
            ->assertRedirect(route('bookings.my-bookings'));

        $booking = BookingModel::query()->sole();
        $this->assertSame(self::OWN_TABLE, $booking->game_table_id);
        $this->assertSame(self::OWN_CAMPAIGN, $booking->campaign_id);
    }

    public function test_the_table_of_somebody_else_is_refused_even_when_anybody_may_see_it(): void
    {
        $this->actingAs($this->booker);

        $this->book(['game_table_id' => self::OTHERS_TABLE])->assertSessionHasErrors(['field_values.game_table_id']);

        $this->assertSame(0, BookingModel::query()->count());
    }

    public function test_nobody_else_may_link_a_booking_to_the_bookers_table(): void
    {
        $this->actingAs(UserModel::factory()->create());

        $this->book(['game_table_id' => self::OWN_TABLE])->assertSessionHasErrors(['field_values.game_table_id']);

        $this->assertSame(0, BookingModel::query()->count());
    }

    public function test_while_impersonating_the_form_offers_what_the_impersonated_account_organizes(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);
        $this->app->make(ImpersonationServiceInterface::class)->start($admin, $this->booker, ImpersonationMode::ReadOnly);

        $this->get(route('bookings.index'))->assertOk()->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page->where(
                'resources.0.field_definitions',
                fn ($fields): bool => $this->optionsOf($fields, 'game_table_id') === [self::OWN_TABLE]
                    && $this->optionsOf($fields, 'campaign_id') === [self::OWN_CAMPAIGN],
            ),
        );
    }

    /**
     * @param  iterable<mixed>  $fields
     * @return list<string>|null
     */
    private function optionsOf(iterable $fields, string $key): ?array
    {
        foreach ($fields as $field) {
            if (is_array($field) && ($field['key'] ?? null) === $key) {
                return array_values(array_map(static fn (array $option): string => (string) $option['value'], (array) $field['options']));
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $fieldValues
     * @return TestResponse<Response>
     */
    private function book(array $fieldValues): TestResponse
    {
        return $this->from(route('bookings.index'))->post(route('bookings.store'), [
            'resource_id' => $this->room->id,
            'date' => '2026-11-12',
            'start_time' => '17:00',
            'end_time' => '18:00',
            'field_values' => $fieldValues,
        ]);
    }

    /**
     * What game-tables would answer through the core: the booker organizes one table and one
     * campaign; another table is published, so anybody finds it.
     */
    private function lookupOrganizedBy(UserModel $booker): LinkableRecordLookupInterface
    {
        return new readonly class($booker->id) implements LinkableRecordLookupInterface
        {
            public function __construct(private string $bookerId) {}

            public function find(string $type, string $id, string $viewerId): ?LinkableRecordDTO
            {
                return null;
            }

            public function findMany(string $type, array $ids, string $viewerId): array
            {
                return [];
            }

            public function search(string $type, string $query, string $viewerId, int $limit = self::DEFAULT_SEARCH_LIMIT): array
            {
                return $type === 'game-table' ? [new LinkableRecordDTO(type: $type, id: '0199f0a0-0000-7000-8000-000000000012', title: 'Mesa de otra persona')] : [];
            }

            public function organizedBy(string $type, Authenticatable $organizer, int $limit = self::DEFAULT_SEARCH_LIMIT): array
            {
                if ($organizer->getAuthIdentifier() !== $this->bookerId) {
                    return [];
                }

                return match ($type) {
                    'game-table' => [new LinkableRecordDTO(type: $type, id: '0199f0a0-0000-7000-8000-000000000011', title: 'Mi mesa')],
                    'campaign' => [new LinkableRecordDTO(type: $type, id: '0199f0a0-0000-7000-8000-000000000021', title: 'Mi campaña')],
                    default => [],
                };
            }

            public function types(): array
            {
                return ['game-table' => 'Mesa de rol', 'campaign' => 'Campaña'];
            }
        };
    }
}
