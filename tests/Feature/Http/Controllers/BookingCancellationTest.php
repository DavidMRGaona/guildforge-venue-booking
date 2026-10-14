<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Modules\ModuleTestCase;

final class BookingCancellationTest extends ModuleTestCase
{
    use CreatesUsersWithPermissions;

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

    public function test_a_user_cannot_cancel_another_users_booking(): void
    {
        $this->actingAs(UserModel::factory()->create())
            ->delete("/reservas/{$this->booking->id}")
            ->assertForbidden()
            ->assertSee(__('venue-bookings::messages.frontend.booking_cancel_forbidden'));

        $this->assertDatabaseHas('venuebookings_bookings', [
            'id' => $this->booking->id,
            'status' => 'confirmed',
            'cancelled_at' => null,
        ]);
    }

    public function test_a_user_can_cancel_their_own_booking(): void
    {
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
        $this->actingAs($this->editorWithPermissions(['venuebookings:bookings.cancel_any']))
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
}
