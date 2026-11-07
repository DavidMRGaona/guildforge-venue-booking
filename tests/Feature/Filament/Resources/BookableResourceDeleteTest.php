<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Filament\Resources;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\DeleteAction;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\VenueBookings\Filament\Resources\BookableResourceResource;
use Modules\VenueBookings\Filament\Resources\BookableResourceResource\Pages\EditBookableResource;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Deleting a space deletes every booking it has, past and future, and cannot be undone:
 * the panel explains it and asks for a second, explicit confirmation.
 */
final class BookableResourceDeleteTest extends ModuleTestCase
{
    use CreatesUsersWithPermissions;
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    private BookableResourceModel $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([BookableResourceResource::class]);
        $this->actingAsPanelAdmin();

        $this->room = BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sala grande',
            'slug' => 'sala-grande',
            'status' => 'active',
            'sort_order' => 0,
        ]);
    }

    public function test_the_confirmation_says_how_many_bookings_will_be_deleted(): void
    {
        $this->booking(now()->subDays(5)->toDateString());
        $this->booking(now()->addDays(2)->toDateString());
        $this->booking(now()->addDays(9)->toDateString());

        $this->assertSame(
            'Se borrarán también sus 3 reservas. 2 de ellas son futuras. No se puede deshacer.',
            $this->modalDescription(),
        );
    }

    public function test_the_confirmation_of_a_space_with_one_booking_uses_the_singular(): void
    {
        $this->booking(now()->subDay()->toDateString());

        $this->assertSame(
            'Se borrará también su única reserva. No se puede deshacer.',
            $this->modalDescription(),
        );
    }

    public function test_the_confirmation_of_an_empty_space_says_it_has_no_bookings(): void
    {
        $this->assertSame(
            'Este espacio no tiene reservas.',
            $this->modalDescription(),
        );
    }

    public function test_the_space_is_not_deleted_without_the_checkbox(): void
    {
        $this->booking(now()->addDay()->toDateString());

        Livewire::test(EditBookableResource::class, ['record' => $this->room->getRouteKey()])
            ->callAction(DeleteAction::class)
            ->assertHasActionErrors(['confirmation']);

        Livewire::test(EditBookableResource::class, ['record' => $this->room->getRouteKey()])
            ->callAction(DeleteAction::class, data: ['confirmation' => false])
            ->assertHasActionErrors(['confirmation']);

        $this->assertDatabaseHas('venuebookings_resources', ['id' => $this->room->id]);
        $this->assertSame(1, BookingModel::query()->where('resource_id', $this->room->id)->count());
    }

    public function test_the_space_and_its_bookings_are_deleted_with_the_checkbox(): void
    {
        $this->booking(now()->subDay()->toDateString());
        $this->booking(now()->addDay()->toDateString());

        Livewire::test(EditBookableResource::class, ['record' => $this->room->getRouteKey()])
            ->callAction(DeleteAction::class, data: ['confirmation' => true])
            ->assertHasNoActionErrors();

        $this->assertDatabaseMissing('venuebookings_resources', ['id' => $this->room->id]);
        $this->assertSame(0, BookingModel::query()->where('resource_id', $this->room->id)->count());
    }

    private function booking(string $date): BookingModel
    {
        return BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->room->id,
            'user_id' => UserModel::factory()->create()->id,
            'date' => $date,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'confirmed',
        ]);
    }

    private function modalDescription(): string
    {
        $page = Livewire::test(EditBookableResource::class, ['record' => $this->room->getRouteKey()])
            ->mountAction(DeleteAction::class);

        $livewire = $page->instance();
        $this->assertInstanceOf(EditBookableResource::class, $livewire);

        $description = $livewire->getMountedAction()?->getModalDescription() ?? '';

        return $description instanceof Htmlable ? $description->toHtml() : $description;
    }
}
