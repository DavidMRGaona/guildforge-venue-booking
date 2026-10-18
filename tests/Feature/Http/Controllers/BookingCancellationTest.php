<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Http\Controllers;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Modules\VenueBookings\Infrastructure\Services\ProfileBookingsDataProvider;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Modules\ModuleTestCase;

final class BookingCancellationTest extends ModuleTestCase
{
    use CreatesUsersWithPermissions;

    private const CANCEL_OWN = 'venuebookings:bookings.cancel_own';

    private const CANCEL_ANY = 'venuebookings:bookings.cancel_any';

    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    private BookableResourceModel $room;

    private UserModel $owner;

    private BookingModel $booking;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->room = BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sala grande',
            'slug' => 'sala-grande',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $this->owner = UserModel::factory()->create();

        $this->booking = BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->room->id,
            'user_id' => $this->owner->id,
            'date' => now()->addDays(3)->toDateString(),
            'start_time' => '18:00',
            'end_time' => '20:00',
            'status' => 'confirmed',
        ]);
    }

    public function test_an_owner_cancelling_an_already_cancelled_future_booking_gets_a_friendly_error(): void
    {
        $this->grant($this->owner, [self::CANCEL_OWN]);
        $this->booking->update(['status' => 'cancelled']);

        $this->actingAs($this->owner)
            ->delete("/reservas/{$this->booking->id}")
            ->assertRedirect()
            ->assertSessionHas('error', 'Esta reserva ya no se puede cancelar.');

        $this->assertDatabaseHas('venuebookings_bookings', [
            'id' => $this->booking->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_a_stranger_cancelling_an_already_cancelled_booking_still_gets_403(): void
    {
        $stranger = UserModel::factory()->create();
        $this->grant($stranger, [self::CANCEL_OWN]);
        $this->booking->update(['status' => 'cancelled']);

        $this->actingAs($stranger)
            ->delete("/reservas/{$this->booking->id}")
            ->assertForbidden();
    }

    public function test_a_user_with_cancel_own_cannot_cancel_another_users_booking(): void
    {
        $other = UserModel::factory()->create();
        $this->grant($other, [self::CANCEL_OWN]);

        $this->actingAs($other)
            ->delete("/reservas/{$this->booking->id}")
            ->assertForbidden()
            ->assertSee(__('venue-bookings::messages.frontend.booking_cancel_forbidden'));

        $this->assertDatabaseHas('venuebookings_bookings', [
            'id' => $this->booking->id,
            'status' => 'confirmed',
            'cancelled_at' => null,
        ]);
    }

    public function test_an_owner_without_cancel_own_cannot_cancel_their_booking(): void
    {
        $this->actingAs($this->owner)
            ->delete("/reservas/{$this->booking->id}")
            ->assertForbidden()
            ->assertSee(__('venue-bookings::messages.frontend.booking_cancel_forbidden'));

        $this->assertDatabaseHas('venuebookings_bookings', [
            'id' => $this->booking->id,
            'status' => 'confirmed',
            'cancelled_at' => null,
        ]);
    }

    public function test_an_owner_with_cancel_own_can_cancel_their_booking(): void
    {
        $this->grant($this->owner, [self::CANCEL_OWN]);

        $this->actingAs($this->owner)
            ->delete("/reservas/{$this->booking->id}")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('venuebookings_bookings', [
            'id' => $this->booking->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_a_user_allowed_to_cancel_any_booking_can_cancel_another_users_booking(): void
    {
        $this->actingAs($this->editorWithPermissions([self::CANCEL_ANY]))
            ->delete("/reservas/{$this->booking->id}")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('venuebookings_bookings', [
            'id' => $this->booking->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_a_missing_booking_answers_like_another_users_booking(): void
    {
        $this->actingAs(UserModel::factory()->create())
            ->delete('/reservas/'.Str::uuid())
            ->assertForbidden()
            ->assertSee(__('venue-bookings::messages.frontend.booking_cancel_forbidden'));
    }

    public function test_a_malformed_booking_id_answers_like_another_users_booking(): void
    {
        $this->actingAs(UserModel::factory()->create())
            ->delete('/reservas/not-a-uuid')
            ->assertForbidden()
            ->assertSee(__('venue-bookings::messages.frontend.booking_cancel_forbidden'));
    }

    public function test_a_reason_that_is_not_text_is_rejected(): void
    {
        $this->grant($this->owner, [self::CANCEL_OWN]);

        $this->actingAs($this->owner)
            ->deleteJson("/reservas/{$this->booking->id}", ['reason' => ['not', 'text']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->assertDatabaseHas('venuebookings_bookings', [
            'id' => $this->booking->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_my_bookings_offers_cancelling_only_when_the_server_would_allow_it(): void
    {
        $this->actingAs($this->owner)
            ->get('/reservas/mis-reservas')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canCancel', false));

        $this->grant($this->owner, [self::CANCEL_OWN]);
        $this->actingAs($this->owner)
            ->get('/reservas/mis-reservas')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canCancel', true));

        $this->actingAs($this->editorWithPermissions([self::CANCEL_ANY]))
            ->get('/reservas/mis-reservas')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canCancel', true));
    }

    public function test_the_profile_bookings_offer_cancelling_only_when_the_server_would_allow_it(): void
    {
        $provider = $this->app->make(ProfileBookingsDataProvider::class);

        $this->assertFalse($provider->getDataForUser($this->owner->id)['canCancel'] ?? null);

        $this->grant($this->owner, [self::CANCEL_OWN]);
        $this->assertTrue($provider->getDataForUser($this->owner->id)['canCancel'] ?? null);
    }

    /**
     * Gives a plain (non-panel) user the permissions through a role of their own.
     *
     * @param  array<string>  $permissionKeys
     */
    private function grant(UserModel $user, array $permissionKeys): void
    {
        $role = RoleModel::create(['name' => 'grant_'.uniqid(), 'display_name' => 'Grant']);

        foreach ($permissionKeys as $key) {
            [$resource, $action] = explode('.', $key, 2);
            $permission = PermissionModel::firstOrCreate(
                ['key' => $key],
                ['label' => $key, 'resource' => $resource, 'action' => $action],
            );
            $role->permissions()->attach($permission->id);
        }

        $user->roles()->attach($role->id);
        $this->app->make(AuthorizationServiceInterface::class)->invalidateCache();
    }
}
