<?php

declare(strict_types=1);

namespace Modules\VenueBookings;

use Throwable;
use Modules\VenueBookings\Filament\Resources\BookableResourceResource;
use Modules\VenueBookings\Filament\Resources\BookingResource;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Policies\BookableResourcePolicy;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Modules\VenueBookings\Policies\BookingPolicy;
use Modules\VenueBookings\Filament\Pages\VenueBookingsSettings;
use Modules\VenueBookings\Filament\Widgets\TodayBookingsWidget;
use Modules\VenueBookings\Filament\Widgets\UpcomingBookingsWidget;
use Filament\Resources\Resource;
use Filament\Pages\Page;
use Filament\Widgets\Widget;
use App\Application\Modules\DTOs\ModuleRouteDTO;
use App\Application\Modules\DTOs\NavigationItemDTO;
use App\Application\Modules\DTOs\PagePrefixDTO;
use App\Application\Modules\DTOs\PermissionDTO;
use App\Application\Modules\DTOs\SlotRegistrationDTO;
use App\Application\Services\SettingsServiceInterface;
use App\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Inertia\Inertia;
use Modules\VenueBookings\Application\Services\BookingEligibilityServiceInterface;
use Modules\VenueBookings\Application\Services\BookingFieldConfigServiceInterface;
use Modules\VenueBookings\Application\Services\BookingQueryServiceInterface;
use Modules\VenueBookings\Application\Services\BookingServiceInterface;
use Modules\VenueBookings\Application\Services\ModuleIntegrationServiceInterface;
use Modules\VenueBookings\Application\Services\SlotAvailabilityServiceInterface;
use Modules\VenueBookings\Console\Commands\MigrateSettingsToDatabase;
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
use Modules\VenueBookings\Infrastructure\Services\BookingRules;
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

        $this->loadSettingsFromDatabase();

        // Repository bindings
        $this->app->bind(BookableResourceRepositoryInterface::class, EloquentBookableResourceRepository::class);
        $this->app->bind(BookingRepositoryInterface::class, EloquentBookingRepository::class);
        $this->app->bind(OperatingScheduleRepositoryInterface::class, EloquentOperatingScheduleRepository::class);

        // Service bindings
        $this->app->bind(BookingServiceInterface::class, BookingService::class);
        $this->app->bind(BookingQueryServiceInterface::class, BookingQueryService::class);
        $this->app->bind(BookingEligibilityServiceInterface::class, BookingEligibilityService::class);
        $this->app->bind(SlotAvailabilityServiceInterface::class, function ($app) {
            return new SlotAvailabilityService(
                $app->make(OperatingScheduleRepositoryInterface::class),
                $app->make(BookingRepositoryInterface::class),
                $app->make(BookingRules::class),
            );
        });
        $this->app->bind(BookingFieldConfigServiceInterface::class, BookingFieldConfigService::class);
        $this->app->bind(ModuleIntegrationServiceInterface::class, ModuleIntegrationService::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->registerEventListeners();
        $this->registerCommands();
        $this->registerSchedule();
        $this->shareProfileBookings();
    }

    private function loadSettingsFromDatabase(): void
    {
        try {
            $settingsService = app(SettingsServiceInterface::class);
            $value = $settingsService->get('module_settings:venue-bookings');

            if ($value !== null && $value !== '') {
                $decoded = json_decode((string) $value, true);
                if (is_array($decoded)) {
                    config()->set('modules.settings.venue-bookings', $decoded);
                }
            }
        } catch (Throwable) {
            // DB unavailable (fresh install, migration in progress).
            // File-based settings from parent::register() remain as fallback.
        }
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
                MigrateSettingsToDatabase::class,
                SendBookingRemindersCommand::class,
            ]);
        }
    }

    private function registerSchedule(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('venue-bookings:send-reminders')
                ->hourly()
                // A run takes seconds; a killed one must not block reminders for a day.
                ->withoutOverlapping(10);
        });
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
     * @return array<class-string<Resource>>
     */
    public function registerFilamentResources(): array
    {
        return [
            BookableResourceResource::class,
            BookingResource::class,
        ];
    }

    /**
     * @return array<class-string, class-string>
     */
    public function registerPolicies(): array
    {
        return [
            BookableResourceModel::class => BookableResourcePolicy::class,
            BookingModel::class => BookingPolicy::class,
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
                // Granted by the 2026_10_14_200001_grant_cancel_own_to_member_role migration
                roles: ['member'],
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
     * @return array<\Filament\Schemas\Components\Component>
     */
    public function getSettingsSchema(): array
    {
        return VenueBookingsSettings::getFormSchemaComponents();
    }

    /**
     * @return array<class-string<Page>>
     */
    public function registerFilamentPages(): array
    {
        return [
            VenueBookingsSettings::class,
        ];
    }

    /**
     * @return array<class-string<Widget>>
     */
    public function registerFilamentWidgets(): array
    {
        return [
            TodayBookingsWidget::class,
            UpcomingBookingsWidget::class,
        ];
    }
}
