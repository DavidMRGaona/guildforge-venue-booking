<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Modules\VenueBookings\Infrastructure\Services\ProfileBookingsDataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Who booked is seen only by the booking team (in the panel and the detail for bookings.manage)
 * and by the person: no public or member answer carries the account id, name or email of whoever
 * booked. The owner learns which bookings are theirs through a flag, never through ids.
 */
final class BookerPrivacyTest extends ModuleTestCase
{
    use CreatesUsersWithPermissions;

    private const string MANAGE = 'venuebookings:bookings.manage';

    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    private BookableResourceModel $room;

    private UserModel $booker;

    private BookingModel $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->room = BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sala grande',
            'slug' => 'sala-grande',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $this->booker = UserModel::factory()->create([
            'name' => 'Persona Reservante',
            'display_name' => 'Alias Reservante',
            'email' => 'reservante@example.com',
        ]);

        $this->booking = $this->bookingFor($this->booker, '10:00', '12:00');
    }

    public function test_the_public_calendar_does_not_tell_a_guest_who_booked(): void
    {
        $response = $this->calendarEvents()->assertOk()->assertJsonCount(1);

        $this->assertNothingIdentifiesTheBooker($response);
        $response->assertJsonPath('0.is_mine', false);
    }

    public function test_the_public_calendar_does_not_tell_another_member_who_booked(): void
    {
        $this->actingAs(UserModel::factory()->create());

        $response = $this->calendarEvents()->assertOk()->assertJsonCount(1);

        $this->assertNothingIdentifiesTheBooker($response);
        $response->assertJsonPath('0.is_mine', false);
    }

    public function test_the_owner_sees_their_own_bookings_marked_and_nobody_elses(): void
    {
        $other = UserModel::factory()->create(['email' => 'otra@example.com']);
        $othersBooking = $this->bookingFor($other, '16:00', '18:00');

        $this->actingAs($this->booker);

        /** @var list<array<string, mixed>> $list */
        $list = $this->calendarEvents()->assertOk()->json();
        $events = array_column($list, null, 'id');

        $this->assertTrue($events[$this->booking->id]['is_mine']);
        $this->assertFalse($events[$othersBooking->id]['is_mine']);
        foreach ($events as $event) {
            $this->assertArrayNotHasKey('user_id', $event);
        }
        $this->assertStringNotContainsString($other->id, (string) json_encode($events));
    }

    public function test_the_booking_team_gets_no_ids_in_the_calendar_and_still_sees_who_booked_in_the_detail(): void
    {
        $this->actingAs($this->editorWithPermissions([self::MANAGE]));

        $this->assertNothingIdentifiesTheBooker($this->calendarEvents()->assertOk());

        $this->getJson(route('bookings.api.show', $this->booking->id))
            ->assertOk()
            ->assertJsonPath('user_name', 'Persona Reservante')
            ->assertJsonMissingPath('user_id');
    }

    public function test_the_booking_detail_does_not_tell_a_guest_or_a_member_who_booked(): void
    {
        $this->assertNothingIdentifiesTheBooker(
            $this->getJson(route('bookings.api.show', $this->booking->id))->assertOk(),
        );

        $this->actingAs(UserModel::factory()->create());

        $this->assertNothingIdentifiesTheBooker(
            $this->getJson(route('bookings.api.show', $this->booking->id))->assertOk(),
        );
    }

    public function test_my_bookings_carry_no_account_id_or_name(): void
    {
        $this->actingAs($this->booker)
            ->get('/reservas/mis-reservas')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('bookings', 1)
                ->where('bookings.0.id', $this->booking->id)
                ->missing('bookings.0.user_id')
                ->missing('bookings.0.user_name'));
    }

    public function test_the_profile_bookings_carry_no_account_id_or_name(): void
    {
        $data = $this->app->make(ProfileBookingsDataProvider::class)->getDataForUser($this->booker->id);

        $this->assertNotNull($data);
        $this->assertCount(1, $data['upcoming']);
        $this->assertArrayNotHasKey('user_id', $data['upcoming'][0]);
        $this->assertArrayNotHasKey('user_name', $data['upcoming'][0]);
    }

    /**
     * @return TestResponse<Response>
     */
    private function calendarEvents(): TestResponse
    {
        return $this->getJson(route('bookings.api.events', [
            'resource_id' => $this->room->id,
            'start' => now()->toDateString(),
            'end' => now()->addWeeks(2)->toDateString(),
        ]));
    }

    /**
     * @param  TestResponse<Response>  $response
     */
    private function assertNothingIdentifiesTheBooker(TestResponse $response): void
    {
        $body = $response->getContent();

        $this->assertIsString($body);
        foreach (['user_id', 'user_name', 'user_email', 'email'] as $key) {
            $this->assertStringNotContainsString('"'.$key.'"', $body);
        }
        foreach ([$this->booker->id, 'Persona Reservante', 'Alias Reservante', 'reservante@example.com'] as $value) {
            $this->assertStringNotContainsString($value, $body);
        }
    }

    private function bookingFor(UserModel $user, string $start, string $end): BookingModel
    {
        return BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->room->id,
            'user_id' => $user->id,
            'date' => now()->addWeek()->toDateString(),
            'start_time' => $start,
            'end_time' => $end,
            'status' => 'confirmed',
        ]);
    }
}
