<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Infrastructure\Knowledge;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Knowledge\Services\KnowledgeQueryServiceInterface;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\ValueObjects\GuideId;
use App\Filament\Pages\KnowledgeBasePage;
use App\Filament\Support\PanelGuideUrl;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\VenueBookings\Filament\Pages\VenueBookingsSettings;
use Modules\VenueBookings\Filament\Resources\BookableResourceResource;
use Modules\VenueBookings\Filament\Resources\BookableResourceResource\Pages\CreateBookableResource;
use Modules\VenueBookings\Filament\Resources\BookableResourceResource\Pages\EditBookableResource;
use Modules\VenueBookings\Filament\Resources\BookableResourceResource\Pages\ListBookableResources;
use Modules\VenueBookings\Filament\Resources\BookingResource;
use Modules\VenueBookings\Filament\Resources\BookingResource\Pages\CreateBooking;
use Modules\VenueBookings\Filament\Resources\BookingResource\Pages\EditBooking;
use Modules\VenueBookings\Filament\Resources\BookingResource\Pages\ListBookings;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Who sees each of the module's guides, and the help button on its panel pages.
 */
final class VenueBookingsGuidesTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    private const array SITE_GUIDES = [
        'venue-bookings.book',
        'venue-bookings.cancel-booking',
        'venue-bookings.my-bookings',
        'venue-bookings.overview',
    ];

    private const array PANEL_GUIDES = [
        'venue-bookings.panel.booking-fields',
        'venue-bookings.panel.create-booking',
        'venue-bookings.panel.manage-bookings',
        'venue-bookings.panel.overview',
        'venue-bookings.panel.schedule',
        'venue-bookings.panel.settings',
        'venue-bookings.panel.spaces',
    ];

    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([BookableResourceResource::class, BookingResource::class], [VenueBookingsSettings::class]);
        $this->artisanCommand('knowledge:sync')->assertSuccessful()->run();
    }

    public function test_every_guide_renders_for_an_admin_without_raw_markup(): void
    {
        $admin = UserModel::factory()->admin()->create();

        $this->assertSame(self::SITE_GUIDES, $this->visibleGuides($admin, GuideAudience::Site));
        $this->assertSame(self::PANEL_GUIDES, $this->visibleGuides($admin, GuideAudience::Panel));

        foreach ([GuideAudience::Site->value => self::SITE_GUIDES, GuideAudience::Panel->value => self::PANEL_GUIDES] as $audience => $ids) {
            foreach ($ids as $id) {
                $guide = $this->queries()->guide($admin, GuideAudience::from($audience), GuideId::fromString($id));
                $this->assertNotNull($guide, $id);
                $this->assertStringNotContainsString('[[', $guide->html, $id);
                $this->assertStringNotContainsString('{{', $guide->html, $id);
                $this->assertStringNotContainsString('[!', $guide->html, $id);
            }
        }
    }

    public function test_the_cancel_guide_follows_the_cancel_permissions(): void
    {
        $member = UserModel::factory()->create();
        $this->assertSame(
            ['venue-bookings.book', 'venue-bookings.my-bookings', 'venue-bookings.overview'],
            $this->visibleGuides($member, GuideAudience::Site),
        );

        $canceller = UserModel::factory()->create();
        $this->grant($canceller, 'venuebookings:bookings.cancel_own');
        $this->assertSame(self::SITE_GUIDES, $this->visibleGuides($canceller, GuideAudience::Site));
    }

    public function test_each_permission_shows_only_its_panel_guides(): void
    {
        $this->assertSame(
            ['venue-bookings.panel.manage-bookings', 'venue-bookings.panel.overview'],
            $this->visibleGuides($this->editorWith('venuebookings:bookings.view_any'), GuideAudience::Panel),
        );
        $this->assertSame(
            ['venue-bookings.panel.booking-fields', 'venue-bookings.panel.overview', 'venue-bookings.panel.schedule', 'venue-bookings.panel.spaces'],
            $this->visibleGuides($this->editorWith('venuebookings:bookings.manage'), GuideAudience::Panel),
        );
        $this->assertSame(
            ['venue-bookings.panel.booking-fields', 'venue-bookings.panel.overview', 'venue-bookings.panel.settings'],
            $this->visibleGuides($this->editorWith('venuebookings:bookings.settings'), GuideAudience::Panel),
        );
        $this->assertSame(
            ['venue-bookings.panel.create-booking'],
            $this->visibleGuides($this->editorWith('venuebookings:bookings.create'), GuideAudience::Panel),
        );
        $this->assertSame([], $this->visibleGuides(UserModel::factory()->editor()->create(), GuideAudience::Panel));
    }

    public function test_the_panel_pages_open_their_guide(): void
    {
        $admin = $this->actingAsPanelAdmin();
        $space = BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sala de pruebas',
            'slug' => 'sala-de-pruebas',
            'status' => 'active',
            'sort_order' => 0,
        ]);
        $booking = BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $space->id,
            'user_id' => $admin->id,
            'date' => now()->addDays(3)->toDateString(),
            'start_time' => '18:00',
            'end_time' => '20:00',
            'status' => 'pending',
        ]);

        $pages = [
            [ListBookableResources::class, [], 'venue-bookings.panel.spaces'],
            [CreateBookableResource::class, [], 'venue-bookings.panel.spaces'],
            [EditBookableResource::class, ['record' => $space->getRouteKey()], 'venue-bookings.panel.spaces'],
            [ListBookings::class, [], 'venue-bookings.panel.manage-bookings'],
            [CreateBooking::class, [], 'venue-bookings.panel.create-booking'],
            [EditBooking::class, ['record' => $booking->getRouteKey()], 'venue-bookings.panel.manage-bookings'],
            [VenueBookingsSettings::class, [], 'venue-bookings.panel.settings'],
        ];

        foreach ($pages as [$page, $parameters, $guide]) {
            Livewire::test($page, $parameters)
                ->assertActionVisible('contextualHelp')
                ->assertActionHasUrl('contextualHelp', KnowledgeBasePage::getUrl(['guia' => $guide]));
        }
    }

    public function test_no_help_button_without_the_guide_permission(): void
    {
        // Lists the bookings, but the create guide needs bookings.create
        $this->actingAs($this->editorWith('venuebookings:bookings.view_any'));

        Livewire::test(ListBookings::class)->assertActionVisible('contextualHelp');
        $this->assertNull(PanelGuideUrl::for('venue-bookings.panel.create-booking'));
    }

    /**
     * @return list<string>
     */
    private function visibleGuides(UserModel $user, GuideAudience $audience): array
    {
        $ids = array_values(array_filter(
            $this->queries()->visibleIds($user, $audience),
            static fn (string $id): bool => str_starts_with($id, 'venue-bookings.'),
        ));
        sort($ids);

        return $ids;
    }

    private function editorWith(string $permissionKey): UserModel
    {
        $user = UserModel::factory()->editor()->create();
        $this->grant($user, $permissionKey);

        return $user;
    }

    private function grant(UserModel $user, string $permissionKey): void
    {
        $role = RoleModel::query()->create(['name' => 'tester-'.uniqid(), 'display_name' => 'Permissions tester']);
        [$resource, $action] = explode('.', $permissionKey, 2);
        $permission = PermissionModel::query()->firstOrCreate(
            ['key' => $permissionKey],
            ['label' => $permissionKey, 'resource' => $resource, 'action' => $action],
        );
        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);

        $this->app->make(AuthorizationServiceInterface::class)->invalidateCache();
    }

    private function queries(): KnowledgeQueryServiceInterface
    {
        return $this->app->make(KnowledgeQueryServiceInterface::class);
    }
}
