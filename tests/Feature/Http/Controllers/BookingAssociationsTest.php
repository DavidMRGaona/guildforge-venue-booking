<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\VenueBookings\Application\Services\ModuleIntegrationServiceInterface;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Modules\VenueBookings\Tests\Support\OpensResourceEveryDay;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\Modules\ModuleTestCase;

/**
 * A booking made on the website is linked to a game table or campaign by its id, chosen among
 * the ones offered to the user; titles, ids from elsewhere and fields that are off are refused.
 */
final class BookingAssociationsTest extends ModuleTestCase
{
    use OpensResourceEveryDay;

    private const string VENUE_TIMEZONE = 'Europe/Madrid';

    private const string TABLE_ID = '0199f0a0-0000-7000-8000-000000000001';

    private const string CAMPAIGN_ID = '0199f0a0-0000-7000-8000-000000000002';

    private const string TOURNAMENT_ID = '0199f0a0-0000-7000-8000-000000000003';

    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    private BookableResourceModel $room;

    private UserModel $user;

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
        config()->set('modules.settings.venue-bookings.associations.game_tables_enabled', true);
        config()->set('modules.settings.venue-bookings.associations.game_tables_visibility', 'authenticated');
        config()->set('modules.settings.venue-bookings.associations.game_tables_required', false);
        config()->set('modules.settings.venue-bookings.associations.campaigns_enabled', true);
        config()->set('modules.settings.venue-bookings.associations.campaigns_visibility', 'authenticated');
        config()->set('modules.settings.venue-bookings.associations.campaigns_required', false);
        config()->set('modules.settings.venue-bookings.associations.tournaments_enabled', true);
        config()->set('modules.settings.venue-bookings.associations.tournaments_visibility', 'authenticated');
        config()->set('modules.settings.venue-bookings.associations.tournaments_required', false);

        // The game-tables module is optional: what it offers to the user is stubbed
        $integration = $this->createStub(ModuleIntegrationServiceInterface::class);
        $integration->method('isGameTablesModuleAvailable')->willReturn(true);
        $integration->method('getUserGameTables')->willReturn([
            ['id' => self::TABLE_ID, 'label' => 'La tumba de los horrores'],
        ]);
        $integration->method('getUserCampaigns')->willReturn([
            ['id' => self::CAMPAIGN_ID, 'label' => 'La maldición de Strahd, martes'],
        ]);
        $integration->method('isTournamentsModuleAvailable')->willReturn(true);
        $integration->method('getAvailableTournaments')->willReturn([
            ['id' => self::TOURNAMENT_ID, 'label' => 'Torneo de primavera'],
        ]);
        $this->app->instance(ModuleIntegrationServiceInterface::class, $integration);

        $this->room = BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sala grande',
            'slug' => 'sala-grande',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $this->openEveryDay($this->room->id, '10:00', '22:00');

        $this->user = UserModel::factory()->create();
    }

    public function test_the_booking_keeps_the_id_of_the_chosen_game_table_campaign_and_tournament(): void
    {
        $this->book(['field_values' => [
            'game_table_id' => self::TABLE_ID,
            'campaign_id' => self::CAMPAIGN_ID,
            'tournament_id' => self::TOURNAMENT_ID,
        ]])->assertRedirect(route('bookings.my-bookings'));

        $booking = BookingModel::query()->sole();
        $this->assertSame(self::TABLE_ID, $booking->game_table_id);
        $this->assertSame(self::CAMPAIGN_ID, $booking->campaign_id);
        $this->assertSame(self::TOURNAMENT_ID, $booking->tournament_id);
        // Stored in their own columns, not among the answers to the form fields
        $this->assertSame([], $booking->field_values ?? []);
    }

    public function test_the_title_of_a_game_table_or_tournament_is_refused(): void
    {
        $this->book(['field_values' => ['game_table_id' => 'La tumba de los horrores', 'tournament_id' => 'Torneo de primavera']])
            ->assertSessionHasErrors(['field_values.game_table_id', 'field_values.tournament_id']);

        $this->assertSame(0, BookingModel::query()->count());
    }

    public function test_a_game_table_or_campaign_not_offered_to_the_user_is_refused(): void
    {
        $this->book(['field_values' => [
            'game_table_id' => (string) Str::uuid(),
            'campaign_id' => (string) Str::uuid(),
        ]])->assertSessionHasErrors(['field_values.game_table_id', 'field_values.campaign_id']);

        $this->assertSame(0, BookingModel::query()->count());
    }

    public function test_ids_sent_outside_the_form_fields_are_ignored(): void
    {
        $this->book([
            'game_table_id' => self::TABLE_ID,
            'campaign_id' => self::CAMPAIGN_ID,
            'tournament_id' => (string) Str::uuid(),
            'event_id' => (string) Str::uuid(),
        ])->assertRedirect(route('bookings.my-bookings'));

        $booking = BookingModel::query()->sole();
        $this->assertNull($booking->game_table_id);
        $this->assertNull($booking->campaign_id);
        $this->assertNull($booking->tournament_id);
        $this->assertNull($booking->event_id);
    }

    public function test_a_game_table_is_ignored_while_the_association_is_off(): void
    {
        config()->set('modules.settings.venue-bookings.associations.game_tables_enabled', false);
        config()->set('modules.settings.venue-bookings.associations.tournaments_enabled', false);

        $this->book(['field_values' => ['game_table_id' => self::TABLE_ID, 'tournament_id' => self::TOURNAMENT_ID]])
            ->assertRedirect(route('bookings.my-bookings'));

        $booking = BookingModel::query()->sole();
        $this->assertNull($booking->game_table_id);
        $this->assertNull($booking->tournament_id);
        $this->assertSame([], $booking->field_values ?? []);
    }

    public function test_a_custom_select_option_with_a_comma_is_accepted(): void
    {
        config()->set('modules.settings.venue-bookings.custom_fields', [
            ['key' => 'table', 'label' => 'Mesa', 'type' => 'select', 'required' => true, 'visibility' => 'public', 'options' => "Sí, con tapete\nNo"],
        ]);

        $this->book(['field_values' => ['table' => 'Sí, con tapete']])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('bookings.my-bookings'));

        $this->assertSame('Sí, con tapete', BookingModel::query()->sole()->field_values[0]['value'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return TestResponse<Response>
     */
    private function book(array $data): TestResponse
    {
        return $this->actingAs($this->user)
            ->from(route('bookings.index'))
            ->post(route('bookings.store'), [
                'resource_id' => $this->room->id,
                'date' => '2026-11-12',
                'start_time' => '17:00',
                'end_time' => '18:00',
                ...$data,
            ]);
    }
}
