<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Filament;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\VenueBookings\Filament\Resources\BookingResource;
use Modules\VenueBookings\Filament\Resources\BookingResource\Pages\EditBooking;
use Modules\VenueBookings\Filament\Resources\BookingResource\Pages\ListBookings;
use Modules\VenueBookings\Filament\Widgets\TodayBookingsWidget;
use Modules\VenueBookings\Filament\Widgets\UpcomingBookingsWidget;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;
use Throwable;

/**
 * Row actions change a booking, so listing bookings (view_any) is not enough:
 * confirm, reject and no-show need manage; cancel needs cancel_any.
 */
final class BookingActionPermissionsTest extends ModuleTestCase
{
    use CreatesUsersWithPermissions;
    use InteractsWithModulePanel;

    private const VIEW_ANY = 'venuebookings:bookings.view_any';

    private const MANAGE = 'venuebookings:bookings.manage';

    private const CANCEL_ANY = 'venuebookings:bookings.cancel_any';

    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    private BookableResourceModel $room;

    private BookingModel $pending;

    private BookingModel $confirmed;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->registerOnAdminPanel([BookingResource::class]);

        $this->room = BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sala grande',
            'slug' => 'sala-grande',
            'status' => 'active',
            'sort_order' => 0,
        ]);
        $this->pending = $this->booking('pending', '10:00', '12:00');
        $this->confirmed = $this->booking('confirmed', '18:00', '20:00');
    }

    public function test_listing_bookings_does_not_show_the_actions_that_change_them(): void
    {
        $this->actingAs($this->editorWithPermissions([self::VIEW_ANY]));

        Livewire::test(ListBookings::class)
            ->assertCanSeeTableRecords([$this->pending, $this->confirmed])
            ->assertTableActionHidden('confirm', $this->pending)
            ->assertTableActionHidden('reject', $this->pending)
            ->assertTableActionHidden('cancel', $this->confirmed)
            ->assertTableActionHidden('no_show', $this->confirmed);
    }

    public function test_listing_bookings_does_not_let_a_forced_call_change_them(): void
    {
        $this->actingAs($this->editorWithPermissions([self::VIEW_ANY]));

        $this->forceTableAction('confirm', $this->pending);
        $this->forceTableAction('reject', $this->pending, ['reason' => 'Sin sitio']);
        $this->forceTableAction('cancel', $this->confirmed, ['reason' => 'Cierre del local']);
        $this->forceTableAction('no_show', $this->confirmed);

        $this->assertStatus('pending', $this->pending);
        $this->assertStatus('confirmed', $this->confirmed);
    }

    public function test_managers_confirm_bookings(): void
    {
        $this->actingAs($this->editorWithPermissions([self::VIEW_ANY, self::MANAGE]));

        Livewire::test(ListBookings::class)
            ->assertTableActionVisible('confirm', $this->pending)
            ->callTableAction('confirm', $this->pending)
            ->assertHasNoTableActionErrors();

        $this->assertStatus('confirmed', $this->pending);
    }

    public function test_managers_reject_bookings(): void
    {
        $this->actingAs($this->editorWithPermissions([self::VIEW_ANY, self::MANAGE]));

        Livewire::test(ListBookings::class)
            ->assertTableActionVisible('reject', $this->pending)
            ->callTableAction('reject', $this->pending, data: ['reason' => 'Sin sitio'])
            ->assertHasNoTableActionErrors();

        $this->assertStatus('rejected', $this->pending);
    }

    public function test_managers_mark_no_shows(): void
    {
        $this->actingAs($this->editorWithPermissions([self::VIEW_ANY, self::MANAGE]));

        Livewire::test(ListBookings::class)
            ->assertTableActionVisible('no_show', $this->confirmed)
            ->callTableAction('no_show', $this->confirmed)
            ->assertHasNoTableActionErrors();

        $this->assertStatus('no_show', $this->confirmed);
    }

    public function test_managers_without_cancel_any_cannot_cancel_from_the_list(): void
    {
        $this->actingAs($this->editorWithPermissions([self::VIEW_ANY, self::MANAGE]));

        Livewire::test(ListBookings::class)->assertTableActionHidden('cancel', $this->confirmed);
        $this->forceTableAction('cancel', $this->confirmed, ['reason' => 'Cierre del local']);

        $this->assertStatus('confirmed', $this->confirmed);
    }

    public function test_staff_allowed_to_cancel_any_booking_cancel_from_the_list(): void
    {
        $this->actingAs($this->editorWithPermissions([self::VIEW_ANY, self::CANCEL_ANY]));

        Livewire::test(ListBookings::class)
            ->assertTableActionHidden('confirm', $this->pending)
            ->assertTableActionVisible('cancel', $this->confirmed)
            ->callTableAction('cancel', $this->confirmed, data: ['reason' => 'Cierre del local'])
            ->assertHasNoTableActionErrors();

        $this->assertStatus('cancelled', $this->confirmed);
    }

    public function test_managers_without_cancel_any_cannot_cancel_from_the_edit_page(): void
    {
        $this->actingAs($this->editorWithPermissions([self::VIEW_ANY, self::MANAGE]));

        try {
            Livewire::test(EditBooking::class, ['record' => $this->confirmed->getRouteKey()])
                ->fillForm(['status' => 'cancelled', 'cancellation_reason' => 'Cierre del local'])
                ->call('save');
        } catch (Throwable) {
            // Refusing with an error is fine; only the booking matters
        }

        $this->assertStatus('confirmed', $this->confirmed);
    }

    public function test_the_edit_page_offers_cancelling_only_to_staff_allowed_to_cancel_any_booking(): void
    {
        $this->actingAs($this->editorWithPermissions([self::VIEW_ANY, self::MANAGE]));
        Livewire::test(EditBooking::class, ['record' => $this->confirmed->getRouteKey()])
            ->assertFormFieldExists('status', fn ($field): bool => ! array_key_exists('cancelled', $field->getOptions()));

        $this->actingAs($this->editorWithPermissions([self::VIEW_ANY, self::MANAGE, self::CANCEL_ANY]));
        Livewire::test(EditBooking::class, ['record' => $this->confirmed->getRouteKey()])
            ->assertFormFieldExists('status', fn ($field): bool => array_key_exists('cancelled', $field->getOptions()));
    }

    public function test_staff_allowed_to_manage_and_cancel_any_booking_cancel_from_the_edit_page(): void
    {
        $this->actingAs($this->editorWithPermissions([self::VIEW_ANY, self::MANAGE, self::CANCEL_ANY]));

        Livewire::test(EditBooking::class, ['record' => $this->confirmed->getRouteKey()])
            ->fillForm(['status' => 'cancelled', 'cancellation_reason' => 'Cierre del local'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertStatus('cancelled', $this->confirmed);
    }

    public function test_the_dashboard_widgets_need_the_permission_to_list_bookings(): void
    {
        $this->actingAs($this->editorWithPermissions([]));
        $this->assertFalse(TodayBookingsWidget::canView());
        $this->assertFalse(UpcomingBookingsWidget::canView());

        $this->actingAs($this->editorWithPermissions([self::VIEW_ANY]));
        $this->assertTrue(TodayBookingsWidget::canView());
        $this->assertTrue(UpcomingBookingsWidget::canView());

        $this->actingAs(UserModel::factory()->admin()->create());
        $this->assertTrue(TodayBookingsWidget::canView());
        $this->assertTrue(UpcomingBookingsWidget::canView());
    }

    /**
     * Calls a row action the way a crafted Livewire request would, whether or not it is shown.
     *
     * @param  array<string, mixed>  $data
     */
    private function forceTableAction(string $name, BookingModel $record, array $data = []): void
    {
        try {
            Livewire::test(ListBookings::class)->callTableAction($name, $record, data: $data);
        } catch (Throwable) {
            // A refused call may surface as an exception; only the booking matters
        }
    }

    private function assertStatus(string $expected, BookingModel $booking): void
    {
        $this->assertSame($expected, BookingModel::query()->findOrFail($booking->id)->getRawOriginal('status'));
    }

    private function booking(string $status, string $start, string $end): BookingModel
    {
        return BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->room->id,
            'user_id' => UserModel::factory()->create()->id,
            'date' => now()->addDays(3)->toDateString(),
            'start_time' => $start,
            'end_time' => $end,
            'status' => $status,
        ]);
    }
}
