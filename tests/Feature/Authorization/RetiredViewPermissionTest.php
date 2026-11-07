<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Authorization;

use App\Application\Authorization\Services\PermissionRegistryInterface;
use App\Application\Modules\DTOs\PermissionDTO;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Filament\Resources\RoleResource\PermissionCatalog;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Livewire\Livewire;
use Modules\VenueBookings\VenueBookingsServiceProvider;
use Tests\Support\Modules\ModuleTestCase;

/**
 * `bookings.view` was offered in the role editor but the panel has no booking detail page. It is
 * no longer registered; the core never deletes permission rows, so sites that granted it keep the
 * row and the grant, which nothing reads, the role editor does not show and saving a role keeps.
 */
final class RetiredViewPermissionTest extends ModuleTestCase
{
    private const string RETIRED = 'venuebookings:bookings.view';

    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    public function test_the_module_no_longer_registers_the_view_permission(): void
    {
        $names = array_map(
            static fn (PermissionDTO $permission): string => $permission->name,
            (new VenueBookingsServiceProvider($this->app))->registerPermissions(),
        );

        $this->assertNotContains('bookings.view', $names);
        $this->assertContains('bookings.view_any', $names);
        $this->assertArrayNotHasKey(self::RETIRED, app(PermissionRegistryInterface::class)->all());
    }

    public function test_the_role_editor_does_not_offer_an_old_row_of_it(): void
    {
        app(PermissionRegistryInterface::class)->syncToDatabase();
        $this->retiredRow();

        $offered = [];
        foreach (app(PermissionCatalog::class)->tabs() as $tab) {
            foreach ($tab->sections as $section) {
                foreach ($section->permissions as $option) {
                    $offered[] = $option->key;
                }
            }
        }

        $this->assertNotContains(self::RETIRED, $offered);
        $this->assertContains('venuebookings:bookings.view_any', $offered);
    }

    public function test_saving_a_role_keeps_an_old_grant_of_it(): void
    {
        $retired = $this->retiredRow();
        $role = RoleModel::query()->create(['name' => 'role-'.uniqid(), 'display_name' => 'Role']);
        $role->permissions()->attach($retired->id);

        $this->actingAs(UserModel::factory()->admin()->create());
        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertContains(self::RETIRED, $role->permissions()->pluck('permissions.key')->all());
    }

    /** The row a site that synced an older version of the module still has */
    private function retiredRow(): PermissionModel
    {
        return PermissionModel::query()->firstOrCreate(
            ['key' => self::RETIRED],
            ['label' => 'Ver detalle de reserva', 'resource' => 'bookings', 'action' => 'view', 'module' => 'venuebookings'],
        );
    }
}
