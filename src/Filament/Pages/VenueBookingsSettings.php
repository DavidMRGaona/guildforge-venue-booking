<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Filament\Pages;

use App\Application\Modules\Services\ModuleContextServiceInterface;
use App\Application\Modules\Services\ModuleSettingsStoreInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Filament\Concerns\ChecksPermissions;
use App\Filament\Concerns\SafeModuleNavigation;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Modules\VenueBookings\Application\Services\ModuleIntegrationServiceInterface;
use Modules\VenueBookings\Domain\Enums\ApprovalMode;
use Modules\VenueBookings\Domain\Enums\BookingFieldType;
use Modules\VenueBookings\Domain\Enums\FieldVisibility;

/**
 * @property Schema $form
 */
final class VenueBookingsSettings extends Page implements HasForms
{
    use ChecksPermissions;
    use InteractsWithForms;
    use SafeModuleNavigation;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.simple-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public static function getNavigationGroup(): string
    {
        return __('venue-bookings::messages.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('venue-bookings::messages.navigation.settings');
    }

    public function getTitle(): string
    {
        return __('venue-bookings::messages.settings.title');
    }

    public static function canAccess(): bool
    {
        // Admins hold every permission.
        return self::userCan('venuebookings:bookings.settings');
    }

    public function mount(ModuleSettingsStoreInterface $settingsStore, ModuleContextServiceInterface $moduleContext): void
    {
        // The shipped defaults (config/settings.php) with the saved values on top, so keys
        // added by a release show their defaults instead of loading empty and being saved off
        $this->form->fill($settingsStore->effective('venue-bookings', $moduleContext->modulePath('venue-bookings')));
    }

    /**
     * Get the form schema components for settings.
     *
     * @return array<Component>
     */
    public static function getFormSchemaComponents(): array
    {
        return [
            Section::make(__('venue-bookings::messages.settings.sections.approval'))
                ->schema([
                    Select::make('approval_mode')
                        ->label(__('venue-bookings::messages.settings.fields.approval_mode'))
                        ->options(ApprovalMode::options())
                        ->native(false)
                        ->required(),
                ]),

            Section::make(__('venue-bookings::messages.settings.sections.limits'))
                ->schema([
                    TextInput::make('max_active_bookings_per_user')
                        ->label(__('venue-bookings::messages.settings.fields.max_active_bookings_per_user'))
                        ->numeric()
                        ->minValue(1)
                        ->required(),

                    TextInput::make('min_advance_minutes')
                        ->label(__('venue-bookings::messages.settings.fields.min_advance_minutes'))
                        ->numeric()
                        ->minValue(0)
                        ->required(),

                    TextInput::make('max_future_days')
                        ->label(__('venue-bookings::messages.settings.fields.max_future_days'))
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                ])
                ->columns(3),

            Section::make(__('venue-bookings::messages.settings.sections.automation'))
                ->schema([
                    Toggle::make('auto_complete_enabled')
                        ->label(__('venue-bookings::messages.settings.fields.auto_complete_enabled'))
                        ->helperText(__('venue-bookings::messages.settings.fields.auto_complete_enabled_help'))
                        ->live(),

                    TextInput::make('auto_complete_after_hours')
                        ->label(__('venue-bookings::messages.settings.fields.auto_complete_after_hours'))
                        ->helperText(__('venue-bookings::messages.settings.fields.auto_complete_after_hours_help'))
                        ->integer()
                        ->minValue(0)
                        ->maxValue(720)
                        ->required()
                        ->visible(fn (Get $get): bool => (bool) $get('auto_complete_enabled')),
                ])
                ->columns(2),

            Section::make(__('venue-bookings::messages.settings.sections.time_block_presets'))
                ->schema([
                    Repeater::make('time_block_presets')
                        ->label(__('venue-bookings::messages.settings.fields.time_block_presets'))
                        ->schema([
                            TextInput::make('label')
                                ->label(__('venue-bookings::messages.settings.fields.time_block_label'))
                                ->required()
                                ->maxLength(100),

                            TextInput::make('open_time')
                                ->label(__('venue-bookings::messages.settings.fields.time_block_open_time'))
                                ->placeholder('HH:MM')
                                ->required(),

                            TextInput::make('close_time')
                                ->label(__('venue-bookings::messages.settings.fields.time_block_close_time'))
                                ->placeholder('HH:MM')
                                ->required(),
                        ])
                        ->columns(3)
                        ->columnSpanFull()
                        ->defaultItems(0)
                        ->addActionLabel(__('venue-bookings::messages.settings.fields.add_time_block')),
                ])
                ->collapsible(),

            Section::make(__('venue-bookings::messages.settings.sections.predefined_fields'))
                ->schema([
                    self::predefinedFieldSchema('activity_name', 'venue-bookings::messages.settings.fields.field_activity_name'),
                    self::predefinedFieldSchema('num_participants', 'venue-bookings::messages.settings.fields.field_num_participants'),
                    self::predefinedFieldSchema('contact_phone', 'venue-bookings::messages.settings.fields.field_contact_phone'),
                    self::predefinedFieldSchema('notes', 'venue-bookings::messages.settings.fields.field_notes'),
                ])
                ->collapsible(),

            Section::make(__('venue-bookings::messages.settings.sections.custom_fields'))
                ->schema([
                    Repeater::make('custom_fields')
                        ->label(__('venue-bookings::messages.settings.fields.custom_fields'))
                        ->schema([
                            TextInput::make('key')
                                ->label(__('venue-bookings::messages.settings.fields.custom_field_key'))
                                ->required()
                                ->maxLength(50),

                            TextInput::make('label')
                                ->label(__('venue-bookings::messages.settings.fields.custom_field_label'))
                                ->required()
                                ->maxLength(100),

                            Select::make('type')
                                ->label(__('venue-bookings::messages.settings.fields.custom_field_type'))
                                ->options(BookingFieldType::options())
                                ->native(false)
                                ->required()
                                ->live(),

                            Toggle::make('required')
                                ->label(__('venue-bookings::messages.settings.fields.custom_field_required')),

                            Select::make('visibility')
                                ->label(__('venue-bookings::messages.settings.fields.custom_field_visibility'))
                                ->options(FieldVisibility::options())
                                ->default(FieldVisibility::Public->value)
                                ->native(false),

                            Textarea::make('options')
                                ->label(__('venue-bookings::messages.settings.fields.custom_field_options'))
                                ->helperText(__('venue-bookings::messages.settings.fields.custom_field_options_help'))
                                ->visible(fn (Get $get): bool => $get('type') === BookingFieldType::Select->value)
                                ->rows(3),
                        ])
                        ->columns(3)
                        ->columnSpanFull()
                        ->defaultItems(0)
                        ->addActionLabel(__('venue-bookings::messages.settings.fields.add_custom_field')),
                ])
                ->collapsible(),

            Section::make(__('venue-bookings::messages.settings.sections.notifications'))
                ->schema([
                    Toggle::make('notifications.notify_on_booking_created')
                        ->label(__('venue-bookings::messages.settings.fields.notify_on_booking_created'))
                        ->helperText(__('venue-bookings::messages.settings.fields.notify_on_booking_created_help')),

                    Toggle::make('notifications.notify_on_booking_received')
                        ->label(__('venue-bookings::messages.settings.fields.notify_on_booking_received'))
                        ->helperText(__('venue-bookings::messages.settings.fields.notify_on_booking_received_help')),

                    Toggle::make('notifications.notify_on_booking_confirmed')
                        ->label(__('venue-bookings::messages.settings.fields.notify_on_booking_confirmed')),

                    Toggle::make('notifications.notify_on_booking_cancelled')
                        ->label(__('venue-bookings::messages.settings.fields.notify_on_booking_cancelled')),

                    Toggle::make('notifications.notify_on_booking_rejected')
                        ->label(__('venue-bookings::messages.settings.fields.notify_on_booking_rejected')),

                    Toggle::make('notifications.notify_on_booking_reminder')
                        ->label(__('venue-bookings::messages.settings.fields.notify_on_booking_reminder')),

                    TextInput::make('notifications.reminder_hours_before')
                        ->label(__('venue-bookings::messages.settings.fields.reminder_hours_before'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(72),
                ])
                ->columns(3),

            Section::make(__('venue-bookings::messages.settings.sections.associations'))
                ->schema([
                    Toggle::make('associations.game_tables_enabled')
                        ->label(__('venue-bookings::messages.settings.fields.game_tables_enabled'))
                        ->live(),
                    Select::make('associations.game_tables_visibility')
                        ->label(__('venue-bookings::messages.settings.fields.field_visibility'))
                        ->options(FieldVisibility::options())
                        ->native(false)
                        ->visible(fn (Get $get): bool => (bool) $get('associations.game_tables_enabled')),

                    Toggle::make('associations.campaigns_enabled')
                        ->label(__('venue-bookings::messages.settings.fields.campaigns_enabled'))
                        ->live(),
                    Select::make('associations.campaigns_visibility')
                        ->label(__('venue-bookings::messages.settings.fields.field_visibility'))
                        ->options(FieldVisibility::options())
                        ->native(false)
                        ->visible(fn (Get $get): bool => (bool) $get('associations.campaigns_enabled')),
                ])
                ->columns(2)
                ->collapsible()
                ->visible(fn (): bool => app(ModuleIntegrationServiceInterface::class)->isGameTablesModuleAvailable()),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(self::getFormSchemaComponents())
            ->statePath('data');
    }

    public function save(SettingsServiceInterface $settingsService): void
    {
        $formData = $this->form->getState();

        $settingsService->set(
            'module_settings:venue-bookings',
            json_encode($formData, JSON_THROW_ON_ERROR),
        );

        // Update in-memory config for the current request
        config()->set('modules.settings.venue-bookings', $formData);

        Notification::make()
            ->title(__('common.saved'))
            ->success()
            ->send();
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('common.save'))
                ->submit('save'),
        ];
    }

    private static function predefinedFieldSchema(string $fieldKey, string $labelKey): Grid
    {
        return Grid::make(3)
            ->schema([
                Toggle::make("predefined_fields.{$fieldKey}.enabled")
                    ->label(__($labelKey))
                    ->live(),

                Toggle::make("predefined_fields.{$fieldKey}.required")
                    ->label(__('venue-bookings::messages.settings.fields.field_required'))
                    ->visible(fn (Get $get): bool => (bool) $get("predefined_fields.{$fieldKey}.enabled")),

                Select::make("predefined_fields.{$fieldKey}.visibility")
                    ->label(__('venue-bookings::messages.settings.fields.field_visibility'))
                    ->options(FieldVisibility::options())
                    ->native(false)
                    ->visible(fn (Get $get): bool => (bool) $get("predefined_fields.{$fieldKey}.enabled")),
            ]);
    }
}
