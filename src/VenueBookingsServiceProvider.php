<?php

declare(strict_types=1);

namespace Modules\VenueBookings;

use App\Application\Activity\Contracts\ActivitySourceInterface;
use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Knowledge\Contracts\KnowledgeCapabilityInterface;
use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Modules\DTOs\ModuleRouteDTO;
use App\Application\Modules\DTOs\NavigationItemDTO;
use App\Application\Modules\DTOs\PagePrefixDTO;
use App\Application\Modules\DTOs\PermissionDTO;
use App\Application\Modules\DTOs\SlotRegistrationDTO;
use App\Application\Proposals\Contracts\EventProposalRequestInterface;
use App\Filament\Support\PanelNavigationGroup;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Modules\ModuleServiceProvider;
use Filament\Pages\Page;
use Filament\Resources\Resource as FilamentResource;
use Filament\Widgets\Widget;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Inertia\Inertia;
use Modules\VenueBookings\Application\Services\BookingEligibilityServiceInterface;
use Modules\VenueBookings\Application\Services\BookingFieldConfigServiceInterface;
use Modules\VenueBookings\Application\Services\BookingLockInterface;
use Modules\VenueBookings\Application\Services\BookingQueryServiceInterface;
use Modules\VenueBookings\Application\Services\BookingServiceInterface;
use Modules\VenueBookings\Application\Services\ModuleIntegrationServiceInterface;
use Modules\VenueBookings\Application\Services\SlotAvailabilityServiceInterface;
use Modules\VenueBookings\Console\Commands\CompletePastBookingsCommand;
use Modules\VenueBookings\Console\Commands\ExpirePastPendingBookingsCommand;
use Modules\VenueBookings\Console\Commands\MigrateSettingsToDatabase;
use Modules\VenueBookings\Console\Commands\SendBookingRemindersCommand;
use Modules\VenueBookings\Domain\Events\BookingCancelled;
use Modules\VenueBookings\Domain\Events\BookingConfirmed;
use Modules\VenueBookings\Domain\Events\BookingCreated;
use Modules\VenueBookings\Domain\Events\BookingMoved;
use Modules\VenueBookings\Domain\Events\BookingRejected;
use Modules\VenueBookings\Domain\Repositories\BookableResourceRepositoryInterface;
use Modules\VenueBookings\Domain\Repositories\BookingRepositoryInterface;
use Modules\VenueBookings\Domain\Repositories\OperatingScheduleRepositoryInterface;
use Modules\VenueBookings\Filament\Pages\VenueBookingsSettings;
use Modules\VenueBookings\Filament\Resources\BookableResourceResource;
use Modules\VenueBookings\Filament\Resources\BookingResource;
use Modules\VenueBookings\Filament\Widgets\TodayBookingsWidget;
use Modules\VenueBookings\Filament\Widgets\UpcomingBookingsWidget;
use Modules\VenueBookings\Infrastructure\Activity\AnnouncesActivityChanges;
use Modules\VenueBookings\Infrastructure\Activity\BookingActivitySource;
use Modules\VenueBookings\Infrastructure\Knowledge\CancelsOwnBookingsCapability;
use Modules\VenueBookings\Infrastructure\Listeners\NotifyMemberOnBookingReceived;
use Modules\VenueBookings\Infrastructure\Listeners\NotifyOnBookingCancelled;
use Modules\VenueBookings\Infrastructure\Listeners\NotifyOnBookingConfirmed;
use Modules\VenueBookings\Infrastructure\Listeners\NotifyOnBookingCreated;
use Modules\VenueBookings\Infrastructure\Listeners\NotifyOnBookingMoved;
use Modules\VenueBookings\Infrastructure\Listeners\NotifyOnBookingRejected;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Repositories\EloquentBookableResourceRepository;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Repositories\EloquentBookingRepository;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Repositories\EloquentOperatingScheduleRepository;
use Modules\VenueBookings\Infrastructure\Proposals\BookingProposalRequest;
use Modules\VenueBookings\Infrastructure\Services\BookingEligibilityService;
use Modules\VenueBookings\Infrastructure\Services\BookingFieldConfigService;
use Modules\VenueBookings\Infrastructure\Services\BookingQueryService;
use Modules\VenueBookings\Infrastructure\Services\BookingRules;
use Modules\VenueBookings\Infrastructure\Services\BookingService;
use Modules\VenueBookings\Infrastructure\Services\CacheBookingLock;
use Modules\VenueBookings\Infrastructure\Services\ModuleIntegrationService;
use Modules\VenueBookings\Infrastructure\Services\ProfileBookingsDataProvider;
use Modules\VenueBookings\Infrastructure\Services\SlotAvailabilityService;
use Modules\VenueBookings\Policies\BookableResourcePolicy;
use Modules\VenueBookings\Policies\BookingPolicy;
use WeakMap;

final class VenueBookingsServiceProvider extends ModuleServiceProvider
{
    public function moduleName(): string
    {
        return 'venue-bookings';
    }

    /** Confirmations and changes to a booking the person made: always sent. */
    public const string MAIL_MY_BOOKINGS = 'venue-bookings.my-bookings';

    public const string MAIL_REMINDERS = 'venue-bookings.reminders';

    public const string MAIL_MANAGEMENT = 'venue-bookings.management';

    public function register(): void
    {
        // The core loads the shipped defaults with the values saved in the panel on top
        parent::register();

        // Repository bindings
        $this->app->bind(BookableResourceRepositoryInterface::class, EloquentBookableResourceRepository::class);
        $this->app->bind(BookingRepositoryInterface::class, EloquentBookingRepository::class);
        $this->app->bind(OperatingScheduleRepositoryInterface::class, EloquentOperatingScheduleRepository::class);

        // Service bindings
        $this->app->bind(BookingServiceInterface::class, BookingService::class);
        $this->app->bind(BookingLockInterface::class, CacheBookingLock::class);
        $this->app->bind(BookingQueryServiceInterface::class, BookingQueryService::class);
        $this->app->bind(BookingEligibilityServiceInterface::class, BookingEligibilityService::class);
        $this->app->bind(SlotAvailabilityServiceInterface::class, fn ($app) => new SlotAvailabilityService(
            $app->make(OperatingScheduleRepositoryInterface::class),
            $app->make(BookingRepositoryInterface::class),
            $app->make(BookingRules::class),
        ));
        $this->app->bind(BookingFieldConfigServiceInterface::class, BookingFieldConfigService::class);
        $this->app->bind(ModuleIntegrationServiceInterface::class, ModuleIntegrationService::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->registerEventListeners();
        $this->registerActivityPublishing();
        $this->registerCommands();
        $this->registerSchedule();
        $this->shareProfileBookings();
    }

    private function registerEventListeners(): void
    {
        Event::listen(
            BookingCreated::class,
            [NotifyOnBookingCreated::class, 'handle']
        );

        Event::listen(
            BookingCreated::class,
            [NotifyMemberOnBookingReceived::class, 'handle']
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

        Event::listen(
            BookingMoved::class,
            [NotifyOnBookingMoved::class, 'handle']
        );
    }

    /**
     * What changes the facts of a booking is published again for the activity contract. Bookings
     * are saved from many places (panel, repository, service, the hourly task), so this listens to
     * the models. A space and an account take their bookings with them through the database, so
     * their completed bookings are asked before and published after.
     */
    private function registerActivityPublishing(): void
    {
        BookingModel::saved(fn (BookingModel $booking) => $this->app->make(AnnouncesActivityChanges::class)->bookingSaved($booking));
        BookingModel::deleted(fn (BookingModel $booking) => $this->app->make(AnnouncesActivityChanges::class)->bookingDeleted($booking));

        /** @var WeakMap<BookableResourceModel, list<string>> $ofSpaces */
        $ofSpaces = new WeakMap;
        BookableResourceModel::deleting(function (BookableResourceModel $space) use ($ofSpaces): void {
            $ofSpaces[$space] = $this->app->make(AnnouncesActivityChanges::class)->completedIdsOfSpace($space);
        });
        BookableResourceModel::deleted(function (BookableResourceModel $space) use ($ofSpaces): void {
            $bookingIds = $ofSpaces[$space] ?? [];
            unset($ofSpaces[$space]);
            $this->app->make(AnnouncesActivityChanges::class)->removed($bookingIds);
        });

        /** @var WeakMap<UserModel, list<string>> $ofAccounts */
        $ofAccounts = new WeakMap;
        UserModel::forceDeleting(function (UserModel $user) use ($ofAccounts): void {
            $ofAccounts[$user] = $this->app->make(AnnouncesActivityChanges::class)->completedIdsOfAccount($user);
        });
        UserModel::forceDeleted(function (UserModel $user) use ($ofAccounts): void {
            $bookingIds = $ofAccounts[$user] ?? [];
            unset($ofAccounts[$user]);
            $this->app->make(AnnouncesActivityChanges::class)->removed($bookingIds);
        });
    }

    /**
     * «Reservas del local»: the bookings that were completed and who made them (core >= 3.86,
     * core#774).
     *
     * @return array<class-string<ActivitySourceInterface>>
     */
    public function registerActivitySources(): array
    {
        return [BookingActivitySource::class];
    }

    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MigrateSettingsToDatabase::class,
                SendBookingRemindersCommand::class,
                CompletePastBookingsCommand::class,
                ExpirePastPendingBookingsCommand::class,
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

            // Confirmed bookings become completed once they ended (plus the configured hours)
            $schedule->command('venue-bookings:complete-past')
                ->hourly()
                ->withoutOverlapping(10);

            // Pending bookings nobody approved expire once they ended
            $schedule->command('venue-bookings:expire-past-pending')
                ->hourly()
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
     * @return array<class-string<FilamentResource>>
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
            ),
            new PermissionDTO(
                name: 'bookings.create',
                label: __('venue-bookings::messages.permissions.create'),
                group: __('venue-bookings::messages.navigation.bookings'),
                module: 'venuebookings',
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
                group: PanelNavigationGroup::Activities->getLabel(),
                sort: 31,
                permissions: ['venuebookings:bookings.manage'],
                module: 'venuebookings',
            ),
            new NavigationItemDTO(
                label: __('venue-bookings::messages.navigation.bookings'),
                route: 'filament.admin.resources.bookings.index',
                icon: 'heroicon-o-calendar-days',
                group: PanelNavigationGroup::Activities->getLabel(),
                sort: 30,
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
            new PagePrefixDTO(prefix: 'VenueBookings', module: $this->moduleName(), usesCoreLayout: true),
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
     * The settings are edited on the module's own page in the settings cluster.
     *
     * @return class-string<Page>
     */
    public function getSettingsPage(): string
    {
        return VenueBookingsSettings::class;
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

    /**
     * The space booking an event proposal may ask for, made when the proposal is approved.
     *
     * @return array<class-string<EventProposalRequestInterface>>
     */
    public function registerEventProposalRequests(): array
    {
        return [BookingProposalRequest::class];
    }

    /**
     * @return array<class-string<KnowledgeCapabilityInterface>>
     */
    public function registerKnowledgeCapabilities(): array
    {
        return [
            CancelsOwnBookingsCapability::class,
        ];
    }

    /**
     * @return list<MailCategoryDTO>
     */
    public function registerMailCategories(): array
    {
        $section = 'venue-bookings::messages.mail_categories.section';

        return [
            MailCategoryDTO::mandatory(
                key: self::MAIL_MY_BOOKINGS,
                section: $section,
                label: 'venue-bookings::messages.mail_categories.my_bookings.label',
                description: 'venue-bookings::messages.mail_categories.my_bookings.description',
                reason: 'venue-bookings::messages.mail_categories.my_bookings.reason',
                site: true,
            ),
            MailCategoryDTO::optional(
                key: self::MAIL_REMINDERS,
                section: $section,
                label: 'venue-bookings::messages.mail_categories.reminders.label',
                description: 'venue-bookings::messages.mail_categories.reminders.description',
                site: true,
            ),
            MailCategoryDTO::optional(
                key: self::MAIL_MANAGEMENT,
                section: $section,
                label: 'venue-bookings::messages.mail_categories.management.label',
                description: 'venue-bookings::messages.mail_categories.management.description',
                audience: static fn (object $user): bool => app(AuthorizationServiceInterface::class)
                    ->can($user, 'venuebookings:bookings.manage'),
                site: true,
            ),
        ];
    }
}
