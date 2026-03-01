<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Filament\Resources;

use App\Filament\Resources\BaseResource;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Modules\VenueBookings\Domain\Enums\BookableResourceStatus;
use Modules\VenueBookings\Domain\Enums\BookingFieldType;
use Modules\VenueBookings\Domain\Enums\FieldVisibility;
use Modules\VenueBookings\Domain\Enums\SchedulingMode;
use Modules\VenueBookings\Filament\Resources\BookableResourceResource\Pages;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Services\BookingSettingsReader;

final class BookableResourceResource extends BaseResource
{
    protected static ?string $model = BookableResourceModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('venue-bookings::messages.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('venue-bookings::messages.navigation.resources');
    }

    public static function getModelLabel(): string
    {
        return __('venue-bookings::messages.model.bookable_resource.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('venue-bookings::messages.model.bookable_resource.plural');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('BookableResourceTabs')
                    ->tabs([
                        Tab::make(__('venue-bookings::messages.tabs.basic_info'))
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('venue-bookings::messages.fields.name'))
                                    ->required()
                                    ->maxLength(255),

                                Textarea::make('description')
                                    ->label(__('venue-bookings::messages.fields.description'))
                                    ->rows(3)
                                    ->columnSpanFull(),

                                TextInput::make('capacity')
                                    ->label(__('venue-bookings::messages.fields.capacity'))
                                    ->numeric()
                                    ->minValue(1),

                                Select::make('status')
                                    ->label(__('venue-bookings::messages.fields.status'))
                                    ->options(BookableResourceStatus::options())
                                    ->default(BookableResourceStatus::Active->value)
                                    ->native(false)
                                    ->required(),

                                TextInput::make('sort_order')
                                    ->label(__('venue-bookings::messages.fields.sort_order'))
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0),
                            ])
                            ->columns(2),

                        Tab::make(__('venue-bookings::messages.tabs.schedule'))
                            ->icon('heroicon-o-clock')
                            ->schema([
                                Group::make([
                                    Select::make('scheduling_mode')
                                        ->label(__('venue-bookings::messages.fields.scheduling_mode'))
                                        ->options(SchedulingMode::options())
                                        ->default(SchedulingMode::TimeSlots->value)
                                        ->native(false)
                                        ->required()
                                        ->live()
                                        ->columnSpanFull(),

                                    TextInput::make('slot_duration_minutes')
                                        ->label(__('venue-bookings::messages.fields.slot_duration_minutes'))
                                        ->numeric()
                                        ->minValue(5)
                                        ->maxValue(480)
                                        ->suffix('min')
                                        ->default(60)
                                        ->required(fn (Get $get): bool => $get('scheduling_mode') !== SchedulingMode::TimeBlocks->value)
                                        ->visible(fn (Get $get): bool => $get('scheduling_mode') !== SchedulingMode::TimeBlocks->value),

                                    TextInput::make('min_consecutive_slots')
                                        ->label(__('venue-bookings::messages.fields.min_consecutive_slots'))
                                        ->numeric()
                                        ->default(1)
                                        ->minValue(1)
                                        ->visible(fn (Get $get): bool => $get('scheduling_mode') !== SchedulingMode::TimeBlocks->value),

                                    TextInput::make('max_consecutive_slots')
                                        ->label(__('venue-bookings::messages.fields.max_consecutive_slots'))
                                        ->numeric()
                                        ->default(4)
                                        ->minValue(1)
                                        ->visible(fn (Get $get): bool => $get('scheduling_mode') !== SchedulingMode::TimeBlocks->value),

                                    Repeater::make('day_schedules')
                                        ->label(__('venue-bookings::messages.fields.day_schedules'))
                                        ->afterStateHydrated(function (Repeater $component, ?array $state): void {
                                            if ($state === null || $state === []) {
                                                return;
                                            }

                                            $needsRekey = false;
                                            foreach (array_keys($state) as $key) {
                                                if (is_int($key)) {
                                                    $needsRekey = true;

                                                    break;
                                                }
                                            }

                                            if ($needsRekey) {
                                                $rekeyed = [];
                                                foreach ($state as $item) {
                                                    $rekeyed[(string) Str::uuid()] = $item;
                                                }
                                                $component->state($rekeyed);
                                            }
                                        })
                                        ->schema([
                                            Select::make('day_of_week')
                                                ->label(__('venue-bookings::messages.fields.day_of_week'))
                                                ->placeholder(__('venue-bookings::messages.fields.select_day'))
                                                ->options([
                                                    1 => __('venue-bookings::messages.days.monday'),
                                                    2 => __('venue-bookings::messages.days.tuesday'),
                                                    3 => __('venue-bookings::messages.days.wednesday'),
                                                    4 => __('venue-bookings::messages.days.thursday'),
                                                    5 => __('venue-bookings::messages.days.friday'),
                                                    6 => __('venue-bookings::messages.days.saturday'),
                                                    0 => __('venue-bookings::messages.days.sunday'),
                                                ])
                                                ->native(false)
                                                ->required(),

                                            TextInput::make('label')
                                                ->label(__('venue-bookings::messages.fields.block_label'))
                                                ->maxLength(100)
                                                ->dehydrated()
                                                ->visible(fn (Get $get): bool => $get('../../scheduling_mode') === SchedulingMode::TimeBlocks->value),

                                            TextInput::make('open_time')
                                                ->label(__('venue-bookings::messages.fields.open_time'))
                                                ->placeholder('HH:MM')
                                                ->required(),

                                            TextInput::make('close_time')
                                                ->label(__('venue-bookings::messages.fields.close_time'))
                                                ->placeholder('HH:MM')
                                                ->required(),

                                            Toggle::make('is_enabled')
                                                ->label(__('venue-bookings::messages.fields.is_enabled'))
                                                ->default(true),
                                        ])
                                        ->columns(5)
                                        ->columnSpanFull()
                                        ->defaultItems(0)
                                        ->reorderable(false)
                                        ->addActionLabel(__('venue-bookings::messages.actions.add_day_schedule'))
                                        ->hintActions([
                                            Action::make('bulkAddDays')
                                                ->label(__('venue-bookings::messages.actions.bulk_add_days'))
                                                ->icon('heroicon-m-plus-circle')
                                                ->form(function (Get $get): array {
                                                    $presets = app(BookingSettingsReader::class)->getTimeBlockPresets();
                                                    $presetOptions = [];
                                                    foreach ($presets as $index => $preset) {
                                                        $presetOptions[$index] = "{$preset['label']} ({$preset['open_time']} - {$preset['close_time']})";
                                                    }

                                                    $isBlockMode = $get('scheduling_mode') === SchedulingMode::TimeBlocks->value;

                                                    return [
                                                        ...($presetOptions !== [] ? [
                                                            Select::make('time_block_preset')
                                                                ->label(__('venue-bookings::messages.fields.time_block_preset'))
                                                                ->options($presetOptions)
                                                                ->native(false)
                                                                ->live()
                                                                ->afterStateUpdated(function (Set $set, ?string $state) use ($presets): void {
                                                                    if ($state !== null && isset($presets[(int) $state])) {
                                                                        $preset = $presets[(int) $state];
                                                                        $set('open_time', $preset['open_time']);
                                                                        $set('close_time', $preset['close_time']);
                                                                        $set('label', $preset['label']);
                                                                    }
                                                                }),
                                                        ] : []),
                                                        CheckboxList::make('days')
                                                            ->label(__('venue-bookings::messages.fields.day_of_week'))
                                                            ->options([
                                                                1 => __('venue-bookings::messages.days.monday'),
                                                                2 => __('venue-bookings::messages.days.tuesday'),
                                                                3 => __('venue-bookings::messages.days.wednesday'),
                                                                4 => __('venue-bookings::messages.days.thursday'),
                                                                5 => __('venue-bookings::messages.days.friday'),
                                                                6 => __('venue-bookings::messages.days.saturday'),
                                                                0 => __('venue-bookings::messages.days.sunday'),
                                                            ])
                                                            ->columns(4)
                                                            ->required(),
                                                        ...($isBlockMode ? [
                                                            TextInput::make('label')
                                                                ->label(__('venue-bookings::messages.fields.block_label'))
                                                                ->maxLength(100),
                                                        ] : []),
                                                        TextInput::make('open_time')
                                                            ->label(__('venue-bookings::messages.fields.open_time'))
                                                            ->placeholder('HH:MM')
                                                            ->live()
                                                            ->required(),
                                                        TextInput::make('close_time')
                                                            ->label(__('venue-bookings::messages.fields.close_time'))
                                                            ->placeholder('HH:MM')
                                                            ->live()
                                                            ->required(),
                                                        Toggle::make('is_enabled')
                                                            ->label(__('venue-bookings::messages.fields.is_enabled'))
                                                            ->default(true),
                                                    ];
                                                })
                                                ->action(function (array $data, Repeater $component): void {
                                                    $existing = $component->getState() ?? [];

                                                    foreach ($data['days'] as $day) {
                                                        $uuid = (string) Str::uuid();
                                                        $existing[$uuid] = [
                                                            'day_of_week' => $day,
                                                            'open_time' => $data['open_time'],
                                                            'close_time' => $data['close_time'],
                                                            'is_enabled' => $data['is_enabled'],
                                                            'label' => $data['label'] ?? null,
                                                        ];
                                                    }

                                                    $component->state($existing);
                                                    $component->callAfterStateUpdated();
                                                }),
                                        ]),
                                ])
                                    ->relationship('operatingSchedule')
                                    ->columns(3)
                                    ->columnSpanFull(),
                            ]),

                        Tab::make(__('venue-bookings::messages.tabs.booking_fields'))
                            ->icon('heroicon-o-clipboard-document-list')
                            ->schema([
                                Section::make(__('venue-bookings::messages.settings.sections.resource_predefined_fields'))
                                    ->schema([
                                        self::predefinedFieldSchema('activity_name', 'venue-bookings::messages.settings.fields.field_activity_name'),
                                        self::predefinedFieldSchema('num_participants', 'venue-bookings::messages.settings.fields.field_num_participants'),
                                        self::predefinedFieldSchema('contact_phone', 'venue-bookings::messages.settings.fields.field_contact_phone'),
                                        self::predefinedFieldSchema('notes', 'venue-bookings::messages.settings.fields.field_notes'),
                                    ])
                                    ->collapsible(),

                                Section::make(__('venue-bookings::messages.settings.sections.resource_custom_fields'))
                                    ->schema([
                                        Repeater::make('field_config.custom_fields')
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
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('venue-bookings::messages.fields.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('venue-bookings::messages.fields.status'))
                    ->badge()
                    ->color(fn (BookableResourceStatus $state): string => $state->color())
                    ->formatStateUsing(fn (BookableResourceStatus $state): string => $state->label()),

                TextColumn::make('capacity')
                    ->label(__('venue-bookings::messages.fields.capacity'))
                    ->numeric()
                    ->sortable(),

                TextColumn::make('bookings_count')
                    ->label(__('venue-bookings::messages.fields.bookings_count'))
                    ->counts('bookings')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('venue-bookings::messages.fields.created_at'))
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->defaultSort('sort_order', 'asc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookableResources::route('/'),
            'create' => Pages\CreateBookableResource::route('/create'),
            'edit' => Pages\EditBookableResource::route('/{record}/edit'),
        ];
    }

    private static function predefinedFieldSchema(string $fieldKey, string $labelKey): Grid
    {
        return Grid::make(3)
            ->schema([
                Toggle::make("field_config.predefined_fields.{$fieldKey}.enabled")
                    ->label(__($labelKey))
                    ->default(false)
                    ->live(),

                Toggle::make("field_config.predefined_fields.{$fieldKey}.required")
                    ->label(__('venue-bookings::messages.settings.fields.field_required'))
                    ->visible(fn (Get $get): bool => (bool) $get("field_config.predefined_fields.{$fieldKey}.enabled")),

                Select::make("field_config.predefined_fields.{$fieldKey}.visibility")
                    ->label(__('venue-bookings::messages.settings.fields.field_visibility'))
                    ->options(FieldVisibility::options())
                    ->default(FieldVisibility::Public->value)
                    ->native(false)
                    ->visible(fn (Get $get): bool => (bool) $get("field_config.predefined_fields.{$fieldKey}.enabled")),
            ]);
    }
}
