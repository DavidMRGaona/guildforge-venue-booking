<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Modules\VenueBookings\Application\Services\BookingFieldConfigServiceInterface;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Modules\ModuleTestCase;

/**
 * What only booking managers see follows bookings.manage, whatever role grants it:
 * who booked, the internal notes and the fields shown «Con permiso».
 */
final class BookingDetailsForManagersTest extends ModuleTestCase
{
    use CreatesUsersWithPermissions;

    private const string MANAGE = 'venuebookings:bookings.manage';

    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    private BookableResourceModel $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->room = BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sala grande',
            'slug' => 'sala-grande',
            'status' => 'active',
            'sort_order' => 0,
            'field_config' => [
                'predefined_fields' => [
                    'activity_name' => ['enabled' => true, 'required' => false, 'visibility' => 'public'],
                    'contact_phone' => ['enabled' => true, 'required' => false, 'visibility' => 'permission'],
                    'notes' => ['enabled' => true, 'required' => false, 'visibility' => 'admin_only'],
                ],
                'custom_fields' => [],
            ],
        ]);
    }

    public function test_a_booking_manager_sees_who_booked_and_the_internal_notes(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->editorWithPermissions([self::MANAGE]))
            ->getJson(route('bookings.api.show', $booking->id))
            ->assertOk()
            ->assertJsonPath('user_name', 'John')
            ->assertJsonPath('admin_notes', 'Llaves en conserjería');
    }

    public function test_without_bookings_manage_who_booked_and_the_notes_stay_hidden(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->editorWithPermissions(['venuebookings:bookings.view_any']))
            ->getJson(route('bookings.api.show', $booking->id))
            ->assertOk()
            ->assertJsonMissingPath('user_name')
            ->assertJsonMissingPath('admin_notes');
    }

    public function test_fields_shown_with_permission_follow_bookings_manage(): void
    {
        $this->assertSame(['activity_name', 'contact_phone'], $this->visibleFieldKeys($this->editorWithPermissions([self::MANAGE])));
        $this->assertSame(['activity_name'], $this->visibleFieldKeys($this->editorWithPermissions(['venuebookings:bookings.view_any'])));
    }

    public function test_fields_for_administrators_only_stay_for_administrators(): void
    {
        $this->assertSame(['activity_name', 'contact_phone', 'notes'], $this->visibleFieldKeys(UserModel::factory()->admin()->create()));
    }

    /**
     * @return list<string>
     */
    private function visibleFieldKeys(UserModel $user): array
    {
        return array_column(app(BookingFieldConfigServiceInterface::class)->getVisibleFields($this->room->id, $user), 'key');
    }

    private function booking(): BookingModel
    {
        return BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->room->id,
            'user_id' => UserModel::factory()->create(['name' => 'John'])->id,
            'date' => now()->addWeek()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'confirmed',
            'admin_notes' => 'Llaves en conserjería',
        ]);
    }
}
