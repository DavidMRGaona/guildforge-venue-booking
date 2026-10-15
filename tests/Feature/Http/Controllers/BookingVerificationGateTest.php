<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Http\Controllers;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Http\Middleware\HandleInertiaRequests;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * What the booking pages rely on to gate booking and cancelling on email verification: the
 * calendar and the profile stay open and say whether the account must verify
 * (auth.user.mustVerifyEmail), the eligibility endpoint answers such an account with the
 * verification 403 (which must never leave the booking form active), and a booking or a
 * cancellation sent anyway lands on the notice. Guests and accounts that need not verify keep
 * today's behaviour.
 */
final class BookingVerificationGateTest extends ModuleTestCase
{
    private const string CUTOFF = '2026-01-01T00:00:00+00:00';

    private const string CANCEL_OWN = 'venuebookings:bookings.cancel_own';

    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    private BookableResourceModel $room;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        // Make the module routes, added after boot, resolvable by name
        app('router')->getRoutes()->refreshNameLookups();

        app(SettingsServiceInterface::class)->set('email_verification_enforced_since', self::CUTOFF);
        app(SettingsServiceInterface::class)->set('auth_email_verification_required', '1');

        $this->room = BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sala grande',
            'slug' => 'sala-grande',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        config()->set('modules.settings.venue-bookings.max_active_bookings_per_user', 10);
        config()->set('modules.settings.venue-bookings.min_advance_minutes', 60);
        config()->set('modules.settings.venue-bookings.max_future_days', 365);
    }

    public function test_the_calendar_tells_an_account_that_must_verify_to_block_booking(): void
    {
        $this->actingAs($this->mustVerifyUser())
            ->get(route('bookings.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('VenueBookings/Index', false)
                ->where('auth.user.mustVerifyEmail', true));
    }

    public function test_the_eligibility_of_an_account_that_must_verify_is_the_verification_403(): void
    {
        $this->actingAs($this->mustVerifyUser())
            ->getJson($this->eligibilityUrl())
            ->assertForbidden()
            ->assertHeader('X-Email-Verification-Required', '1')
            ->assertJsonPath('error', 'email_verification_required')
            ->assertJsonMissingPath('can_book');
    }

    public function test_a_booking_sent_anyway_lands_on_the_notice_and_books_nothing(): void
    {
        $this->actingAs($this->mustVerifyUser())
            ->withHeaders($this->inertiaHeaders())
            ->from(route('bookings.index'))
            ->post(route('bookings.store'), $this->bookingPayload())
            ->assertRedirect(route('verification.notice'));

        $this->assertSame(0, BookingModel::query()->count());
    }

    public function test_the_profile_tells_an_account_that_must_verify_to_block_cancelling(): void
    {
        $user = $this->mustVerifyUser();
        $this->grantCancelOwn($user);
        $this->bookingOf($user);

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.mustVerifyEmail', true)
                ->where('profileBookings.canCancel', true)
                ->where('profileBookings.upcoming.0.is_cancellable', true));
    }

    public function test_a_cancellation_sent_anyway_lands_on_the_notice_and_cancels_nothing(): void
    {
        $user = $this->mustVerifyUser();
        $this->grantCancelOwn($user);
        $booking = $this->bookingOf($user);

        $this->actingAs($user)
            ->withHeaders($this->inertiaHeaders())
            ->from(route('profile.show'))
            ->delete(route('bookings.cancel', $booking->id))
            ->assertRedirect(route('verification.notice'));

        $this->assertDatabaseHas('venuebookings_bookings', ['id' => $booking->id, 'status' => 'confirmed']);
    }

    public function test_my_bookings_never_renders_for_an_account_that_must_verify(): void
    {
        $user = $this->mustVerifyUser();
        $this->grantCancelOwn($user);
        $this->bookingOf($user);

        $this->actingAs($user)
            ->get(route('bookings.my-bookings'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_accounts_that_need_not_verify_book_and_cancel_as_before(): void
    {
        $daysAhead = 7;

        foreach ($this->usersThatNeedNotVerify() as $label => $user) {
            $daysAhead++;

            $this->actingAs($user)
                ->get(route('bookings.index'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->where('auth.user.mustVerifyEmail', false));

            $this->actingAs($user)
                ->getJson($this->eligibilityUrl())
                ->assertOk()
                ->assertJson(['can_book' => true, 'reasons' => []]);

            // Reaches the booking controller instead of the notice
            $this->actingAs($user)
                ->withHeaders($this->inertiaHeaders())
                ->from(route('bookings.index'))
                ->post(route('bookings.store'), $this->bookingPayload($daysAhead))
                ->assertRedirect(route('bookings.my-bookings'))
                ->assertSessionHas('success');
            $this->flushHeaders();

            $this->grantCancelOwn($user);
            $booking = $this->bookingOf($user);

            $this->actingAs($user)
                ->get(route('profile.show'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->where('auth.user.mustVerifyEmail', false));

            $this->actingAs($user)
                ->withHeaders($this->inertiaHeaders())
                ->from(route('profile.show'))
                ->delete(route('bookings.cancel', $booking->id))
                ->assertRedirect(route('profile.show'));
            $this->flushHeaders();

            $this->assertDatabaseHas('venuebookings_bookings', ['id' => $booking->id, 'status' => 'cancelled']);
            $this->assertSame(1, BookingModel::query()
                ->where('user_id', $user->id)
                ->whereDate('date', now()->addDays($daysAhead)->toDateString())
                ->count(), $label);
        }
    }

    public function test_a_guest_sees_the_calendar_and_is_sent_to_log_in_as_before(): void
    {
        $this->get(route('bookings.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('auth.user', null));

        $this->getJson($this->eligibilityUrl())->assertUnauthorized();

        $this->post(route('bookings.store'), $this->bookingPayload())
            ->assertRedirect(route('login'));
    }

    private function mustVerifyUser(): UserModel
    {
        return UserModel::factory()->unverified()->create();
    }

    /**
     * @return array<string, UserModel>
     */
    private function usersThatNeedNotVerify(): array
    {
        return [
            'verified' => UserModel::factory()->create(),
            'unverified before the cutoff' => UserModel::factory()->unverified()->create([
                'created_at' => '2025-06-01 12:00:00',
            ]),
        ];
    }

    private function eligibilityUrl(): string
    {
        return route('bookings.api.eligibility', [
            'resource_id' => $this->room->id,
            'date' => now()->addDays(7)->toDateString(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function bookingPayload(int $daysAhead = 7): array
    {
        return [
            'resource_id' => $this->room->id,
            'date' => now()->addDays($daysAhead)->toDateString(),
            'start_time' => '18:00',
            'end_time' => '20:00',
        ];
    }

    private function bookingOf(UserModel $user): BookingModel
    {
        return BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->room->id,
            'user_id' => $user->id,
            'date' => now()->addDays(3)->toDateString(),
            'start_time' => '18:00',
            'end_time' => '20:00',
            'status' => 'confirmed',
        ]);
    }

    private function grantCancelOwn(UserModel $user): void
    {
        [$resource, $action] = explode('.', self::CANCEL_OWN, 2);
        $permission = PermissionModel::firstOrCreate(
            ['key' => self::CANCEL_OWN],
            ['label' => self::CANCEL_OWN, 'resource' => $resource, 'action' => $action],
        );
        $role = RoleModel::create(['name' => 'grant_'.uniqid(), 'display_name' => 'Grant']);
        $role->permissions()->attach($permission->id);

        $user->roles()->attach($role->id);
        $this->app->make(AuthorizationServiceInterface::class)->invalidateCache();
    }

    /**
     * @return array<string, string>
     */
    private function inertiaHeaders(): array
    {
        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }
}
