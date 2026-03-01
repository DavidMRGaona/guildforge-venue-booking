<?php

declare(strict_types=1);

namespace Modules\VenueBookings;

use App\Application\Modules\DTOs\ModuleRouteDTO;
use App\Application\Modules\DTOs\NavigationItemDTO;
use App\Application\Modules\DTOs\PagePrefixDTO;
use App\Application\Modules\DTOs\PermissionDTO;
use App\Application\Modules\DTOs\SlotRegistrationDTO;
use App\Modules\ModuleServiceProvider;
use Illuminate\Support\Facades\Event;
use Inertia\Inertia;
use Modules\VenueBookings\Application\Services\BookingEligibilityServiceInterface;
use Modules\VenueBookings\Application\Services\BookingFieldConfigServiceInterface;
use Modules\VenueBookings\Application\Services\BookingQueryServiceInterface;
use Modules\VenueBookings\Application\Services\BookingServiceInterface;
use Modules\VenueBookings\Application\Services\ModuleIntegrationServiceInterface;
use Modules\VenueBookings\Application\Services\SlotAvailabilityServiceInterface;
use Modules\VenueBookings\Console\Commands\SendBookingRemindersCommand;
use Modules\VenueBookings\Domain\Events\BookingCancelled;
use Modules\VenueBookings\Domain\Events\BookingConfirmed;
use Modules\VenueBookings\Domain\Events\BookingCreated;
use Modules\VenueBookings\Domain\Events\BookingRejected;
use Modules\VenueBookings\Domain\Repositories\BookableResourceRepositoryInterface;
use Modules\VenueBookings\Domain\Repositories\BookingRepositoryInterface;
use Modules\VenueBookings\Domain\Repositories\OperatingScheduleRepositoryInterface;
use Modules\VenueBookings\Infrastructure\Listeners\NotifyOnBookingCancelled;
use Modules\VenueBookings\Infrastructure\Listeners\NotifyOnBookingConfirmed;
use Modules\VenueBookings\Infrastructure\Listeners\NotifyOnBookingCreated;
use Modules\VenueBookings\Infrastructure\Listeners\NotifyOnBookingRejected;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Repositories\EloquentBookableResourceRepository;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Repositories\EloquentBookingRepository;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Repositories\EloquentOperatingScheduleRepository;
use Modules\VenueBookings\Infrastructure\Services\BookingEligibilityService;
use Modules\VenueBookings\Infrastructure\Services\BookingFieldConfigService;
use Modules\VenueBookings\Infrastructure\Services\BookingQueryService;
use Modules\VenueBookings\Infrastructure\Services\BookingService;
use Modules\VenueBookings\Infrastructure\Services\ModuleIntegrationService;
use Modules\VenueBookings\Infrastructure\Services\ProfileBookingsDataProvider;
use Modules\VenueBookings\Infrastructure\Services\SlotAvailabilityService;

final class VenueBookingsServiceProvider extends ModuleServiceProvider
{
    public function moduleName(): string
    {
        return 'venue-bookings';
    }

    public function register(): void
    {
        parent::register();

        // Repository bindings
        $this->app->bind(BookableResourceRepositoryInterface::class, EloquentBookableResourceRepository::class);
        $this->app->bind(BookingRepositoryInterface::class, EloquentBookingRepository::class);
        $this->app->bind(OperatingScheduleRepositoryInterface::class, EloquentOperatingScheduleRepository::class);

        // Service bindings
        $this->app->bind(BookingServiceInterface::class, BookingService::class);
        $this->app->bind(BookingQueryServiceInterface::class, BookingQueryService::class);
        $this->app->bind(BookingEligibilityServiceInterface::class, BookingEligibilityService::class);
        $this->app->bind(SlotAvailabilityServiceInterface::class, SlotAvailabilityService::class);
        $this->app->bind(BookingFieldConfigServiceInterface::class, BookingFieldConfigService::class);
        $this->app->bind(ModuleIntegrationServiceInterface::class, ModuleIntegrationService::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->registerEventListeners();
        $this->registerCommands();
        $this->shareProfileBookings();
    }

    private function registerEventListeners(): void
    {
        Event::listen(
            BookingCreated::class,
            [NotifyOnBookingCreated::class, 'handle']
        );

        Event::listen(
            BookingConfirmed::class,
            [NotifyOnBookingConfirmed::class, 'handle']
        );

        Event::listen(
            BookingCancelled::class,
            [NotifyOnBookingCancelled::class, 'handle']
        );

        Event::listen(
            BookingRejected::class,
            [NotifyOnBookingRejected::class, 'handle']
        );
    }

    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SendBookingRemindersCommand::class,
            ]);
        }
    }

    /**
     * Share profile bookings data via Inertia for the profile page.
     */
    private function shareProfileBookings(): void
    {
        if (! class_exists(Inertia::class)) {
            return;
        }

        Inertia::share('profileBookings', function (): ?array {
            $route = request()->route();
            if ($route?->getName() !== 'profile.show') {
                return null;
            }

            $user = auth()->user();
            if ($user === null) {
                return null;
            }

            $provider = app(ProfileBookingsDataProvider::class);

            return $provider->getDataForUser($user->id);
        });

        Inertia::share('profileBookingsTotal', function (): ?int {
            $route = request()->route();
            if ($route?->getName() !== 'profile.show') {
                return null;
            }

            $user = auth()->user();
            if ($user === null) {
                return null;
            }

            $provider = app(ProfileBookingsDataProvider::class);
            $data = $provider->getDataForUser($user->id);

            return $data['total'] ?? 0;
        });
    }

    /**
     * @return array<SlotRegistrationDTO>
     */
    public function registerSlots(): array
    {
        return [
            new SlotRegistrationDTO(
                slot: 'profile-sections',
                component: 'components/profile/ProfileBookingsSection.vue',
                module: $this->moduleName(),
                order: 20,
                props: [],
                dataKeys: ['profileBookings'],
                profileTab: [
                    'tabId' => 'bookings',
                    'icon' => 'calendar',
                    'labelKey' => 'venueBookings.profile.tabLabel',
                    'badgeKey' => 'profileBookingsTotal',
                ],
            ),
        ];
    }

    public function onEnable(): void
    {
        // Migration is handled by the module system
    }

    public function onDisable(): void
    {
        // Cleanup if needed
    }

    /**
     * @return array<class-string<\Filament\Resources\Resource>>
     */
    public function registerFilamentResources(): array
    {
        return [
            \Modules\VenueBookings\Filament\Resources\BookableResourceResource::class,
            \Modules\VenueBookings\Filament\Resources\BookingResource::class,
        ];
    }

    /**
     * @return array<class-string, class-string>
     */
    public function registerPolicies(): array
    {
        return [
            \Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel::class => \Modules\VenueBookings\Policies\BookableResourcePolicy::class,
            \Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel::class => \Modules\VenueBookings\Policies\BookingPolicy::class,
        ];
    }

    /**
     * @return array<string, array{icon?: string, sort?: int}>
     */
    public function registerNavigationGroups(): array
    {
        return [
            __('venue-bookings::messages.navigation.group') => [
                'sort' => 14,
            ],
        ];
    }

    /**
     * @return array<PermissionDTO>
     */
    public function registerPermissions(): array
    {
        return [
            new PermissionDTO(
                name: 'bookings.view_any',
                label: __('venue-bookings::messages.permissions.view_any'),
                group: __('venue-bookings::messages.navigation.bookings'),
                module: 'venuebookings',
                roles: ['editor'],
            ),
            new PermissionDTO(
                name: 'bookings.view',
                label: __('venue-bookings::messages.permissions.view'),
                group: __('venue-bookings::messages.navigation.bookings'),
                module: 'venuebookings',
                roles: ['editor'],
            ),
            new PermissionDTO(
                name: 'bookings.create',
                label: __('venue-bookings::messages.permissions.create'),
                group: __('venue-bookings::messages.navigation.bookings'),
                module: 'venuebookings',
                roles: ['editor'],
            ),
            new PermissionDTO(
                name: 'bookings.cancel_own',
                label: __('venue-bookings::messages.permissions.cancel_own'),
                group: __('venue-bookings::messages.navigation.bookings'),
                module: 'venuebookings',
                roles: ['editor'],
            ),
            new PermissionDTO(
                name: 'bookings.cancel_any',
                label: __('venue-bookings::messages.permissions.cancel_any'),
                group: __('venue-bookings::messages.navigation.bookings'),
                module: 'venuebookings',
                roles: [],
            ),
            new PermissionDTO(
                name: 'bookings.manage',
                label: __('venue-bookings::messages.permissions.manage'),
                group: __('venue-bookings::messages.navigation.bookings'),
                module: 'venuebookings',
                roles: [],
            ),
            new PermissionDTO(
                name: 'bookings.settings',
                label: __('venue-bookings::messages.permissions.settings'),
                group: __('venue-bookings::messages.navigation.settings'),
                module: 'venuebookings',
                roles: [],
            ),
        ];
    }

    /**
     * @return array<NavigationItemDTO>
     */
    public function registerNavigation(): array
    {
        return [
            new NavigationItemDTO(
                label: __('venue-bookings::messages.navigation.resources'),
                route: 'filament.admin.resources.bookable-resources.index',
                icon: 'heroicon-o-building-office',
                group: __('venue-bookings::messages.navigation.group'),
                sort: 1,
                permissions: ['venuebookings:bookings.manage'],
                module: 'venuebookings',
            ),
            new NavigationItemDTO(
                label: __('venue-bookings::messages.navigation.bookings'),
                route: 'filament.admin.resources.bookings.index',
                icon: 'heroicon-o-calendar-days',
                group: __('venue-bookings::messages.navigation.group'),
                sort: 2,
                permissions: ['venuebookings:bookings.view_any'],
                module: 'venuebookings',
            ),
        ];
    }

    /**
     * @return array<PagePrefixDTO>
     */
    public function registerPagePrefixes(): array
    {
        return [
            new PagePrefixDTO(prefix: 'VenueBookings', module: $this->moduleName()),
        ];
    }

    /**
     * @return array<ModuleRouteDTO>
     */
    public function registerRoutes(): array
    {
        return [
            new ModuleRouteDTO(
                routeName: 'bookings.index',
                label: __('venue-bookings::messages.routes.bookings'),
                module: $this->moduleName(),
            ),
            new ModuleRouteDTO(
                routeName: 'bookings.my-bookings',
                label: __('venue-bookings::messages.routes.my_bookings'),
                module: $this->moduleName(),
            ),
        ];
    }

    /**
     * @return array<\Filament\Forms\Components\Component>
     */
    public function getSettingsSchema(): array
    {
        return \Modules\VenueBookings\Filament\Pages\VenueBookingsSettings::getFormSchemaComponents();
    }

    /**
     * @return array<class-string<\Filament\Pages\Page>>
     */
    public function registerFilamentPages(): array
    {
        return [
            \Modules\VenueBookings\Filament\Pages\VenueBookingsSettings::class,
        ];
    }

    /**
     * @return array<class-string<\Filament\Widgets\Widget>>
     */
    public function registerFilamentWidgets(): array
    {
        return [
            \Modules\VenueBookings\Filament\Widgets\TodayBookingsWidget::class,
            \Modules\VenueBookings\Filament\Widgets\UpcomingBookingsWidget::class,
        ];
    }
}
