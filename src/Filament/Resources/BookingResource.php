<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Filament\Resources;

use App\Filament\Resources\BaseResource;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Modules\VenueBookings\Application\DTOs\CancelBookingDTO;
use Modules\VenueBookings\Application\Services\BookingFieldConfigServiceInterface;
use Modules\VenueBookings\Application\Services\BookingServiceInterface;
use Modules\VenueBookings\Application\Services\ModuleIntegrationServiceInterface;
use Modules\VenueBookings\Domain\Enums\BookingFieldType;
use Modules\VenueBookings\Domain\Enums\BookingStatus;
use Modules\VenueBookings\Domain\ValueObjects\BookingFieldConfig;
use Modules\VenueBookings\Filament\Resources\BookingResource\Pages;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;

final class BookingResource extends BaseResource
{
    protected static ?string $model = BookingModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('venue-bookings::messages.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('venue-bookings::messages.navigation.bookings');
    }

    public static function getModelLabel(): string
    {
        return __('venue-bookings::messages.model.booking.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('venue-bookings::messages.model.booking.plural');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('resource_id')
                    ->label(__('venue-bookings::messages.fields.resource'))
                    ->options(BookableResourceModel::query()->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->native(false)
                    ->live(),

                Select::make('user_id')
                    ->label(__('venue-bookings::messages.fields.user'))
                    ->options(UserModel::query()->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->native(false),

                DatePicker::make('date')
                    ->label(__('venue-bookings::messages.fields.date'))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->required(),

                TextInput::make('start_time')
                    ->label(__('venue-bookings::messages.fields.start_time'))
                    ->required()
                    ->placeholder('HH:MM'),

                TextInput::make('end_time')
                    ->label(__('venue-bookings::messages.fields.end_time'))
                    ->required()
                    ->placeholder('HH:MM'),

                Select::make('status')
                    ->label(__('venue-bookings::messages.fields.status'))
                    ->options(function (?BookingModel $record): array {
                        if ($record === null) {
                            return BookingStatus::options();
                        }

                        $current = $record->status;
                        $options = [$current->value => $current->label()];

                        foreach (BookingStatus::cases() as $case) {
                            if ($current->canTransitionTo($case)) {
                                $options[$case->value] = $case->label();
                            }
                        }

                        return $options;
                    })
                    ->disabled(fn (string $context): bool => $context === 'create')
                    ->native(false),

                Select::make('event_id')
                    ->label(__('venue-bookings::messages.fields.event'))
                    ->options(
                        EventModel::query()
                            ->where('is_published', true)
                            ->orderBy('start_date', 'desc')
                            ->pluck('title', 'id')
                    )
                    ->searchable()
                    ->native(false),

                Select::make('game_table_id')
                    ->label(__('venue-bookings::messages.fields.game_table'))
                    ->options(function (): array {
                        $integration = app(ModuleIntegrationServiceInterface::class);
                        if (! $integration->isGameTablesModuleAvailable()) {
                            return [];
                        }

                        return array_column($integration->getAvailableGameTables(), 'label', 'id');
                    })
                    ->searchable()
                    ->native(false)
                    ->visible(fn (): bool => app(ModuleIntegrationServiceInterface::class)->isGameTablesModuleAvailable()),

                Select::make('campaign_id')
                    ->label(__('venue-bookings::messages.fields.campaign'))
                    ->options(function (): array {
                        $integration = app(ModuleIntegrationServiceInterface::class);
                        if (! $integration->isGameTablesModuleAvailable()) {
                            return [];
                        }

                        return array_column($integration->getAvailableCampaigns(), 'label', 'id');
                    })
                    ->searchable()
                    ->native(false)
                    ->visible(fn (): bool => app(ModuleIntegrationServiceInterface::class)->isGameTablesModuleAvailable()),

                Section::make(__('venue-bookings::messages.settings.sections.resource_predefined_fields'))
                    ->schema(static function (Get $get): array {
                        $resourceId = $get('resource_id');

                        if ($resourceId === null) {
                            return [];
                        }

                        $fieldConfigService = app(BookingFieldConfigServiceInterface::class);
                        $fields = $fieldConfigService->getEnabledFields($resourceId);

                        return array_map(static fn (BookingFieldConfig $field) => match (BookingFieldType::from($field->type)) {
                            BookingFieldType::Text => TextInput::make("field_values.{$field->key}")
                                ->label($field->label)
                                ->required($field->required),
                            BookingFieldType::Number => TextInput::make("field_values.{$field->key}")
                                ->label($field->label)
                                ->numeric()
                                ->required($field->required),
                            BookingFieldType::Textarea => Textarea::make("field_values.{$field->key}")
                                ->label($field->label)
                                ->required($field->required),
                            BookingFieldType::Select => Select::make("field_values.{$field->key}")
                                ->label($field->label)
                                ->options(array_combine($field->options ?? [], $field->options ?? []))
                                ->native(false)
                                ->required($field->required),
                            BookingFieldType::Toggle => Toggle::make("field_values.{$field->key}")
                                ->label($field->label)
                                ->required($field->required),
                        }, $fields);
                    })
                    ->visible(static function (Get $get): bool {
                        $resourceId = $get('resource_id');

                        if ($resourceId === null) {
                            return false;
                        }

                        return ! empty(
                            app(BookingFieldConfigServiceInterface::class)->getEnabledFields($resourceId)
                        );
                    })
                    ->columnSpanFull(),

                Textarea::make('admin_notes')
                    ->label(__('venue-bookings::messages.fields.admin_notes'))
                    ->rows(3)
                    ->columnSpanFull(),

                Textarea::make('cancellation_reason')
                    ->label(__('venue-bookings::messages.fields.cancellation_reason'))
                    ->disabled(fn (string $context): bool => $context === 'create')
                    ->rows(2)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('resource.name')
                    ->label(__('venue-bookings::messages.fields.resource'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('user.name')
                    ->label(__('venue-bookings::messages.fields.user'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('date')
                    ->label(__('venue-bookings::messages.fields.date'))
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('start_time')
                    ->label(__('venue-bookings::messages.fields.start_time')),

                TextColumn::make('end_time')
                    ->label(__('venue-bookings::messages.fields.end_time')),

                TextColumn::make('status')
                    ->label(__('venue-bookings::messages.fields.status'))
                    ->badge()
                    ->color(fn (BookingStatus $state): string => $state->color())
                    ->formatStateUsing(fn (BookingStatus $state): string => $state->label())
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('venue-bookings::messages.fields.created_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('resource_id')
                    ->label(__('venue-bookings::messages.fields.resource'))
                    ->options(BookableResourceModel::query()->pluck('name', 'id')),

                SelectFilter::make('status')
                    ->label(__('venue-bookings::messages.fields.status'))
                    ->options(BookingStatus::options()),

                Filter::make('date_range')
                    ->form([
                        DatePicker::make('date_from')
                            ->label(__('venue-bookings::messages.filters.date_from'))
                            ->native(false),
                        DatePicker::make('date_until')
                            ->label(__('venue-bookings::messages.filters.date_until'))
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (Builder $query, string $date): Builder => $query->where('date', '>=', $date),
                            )
                            ->when(
                                $data['date_until'],
                                fn (Builder $query, string $date): Builder => $query->where('date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Action::make('confirm')
                    ->label(__('venue-bookings::messages.actions.confirm'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (BookingModel $record): bool => $record->status === BookingStatus::Pending)
                    ->action(function (BookingModel $record): void {
                        $service = app(BookingServiceInterface::class);
                        $service->confirmBooking($record->id);

                        Notification::make()
                            ->title(__('venue-bookings::messages.notifications.booking_confirmed'))
                            ->success()
                            ->send();
                    }),

                Action::make('reject')
                    ->label(__('venue-bookings::messages.actions.reject'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('reason')
                            ->label(__('venue-bookings::messages.fields.rejection_reason'))
                            ->required(),
                    ])
                    ->visible(fn (BookingModel $record): bool => $record->status === BookingStatus::Pending)
                    ->action(function (BookingModel $record, array $data): void {
                        $service = app(BookingServiceInterface::class);
                        $service->rejectBooking($record->id, $data['reason']);

                        Notification::make()
                            ->title(__('venue-bookings::messages.notifications.booking_rejected'))
                            ->success()
                            ->send();
                    }),

                Action::make('cancel')
                    ->label(__('venue-bookings::messages.actions.cancel'))
                    ->icon('heroicon-o-no-symbol')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('reason')
                            ->label(__('venue-bookings::messages.fields.cancellation_reason'))
                            ->required(),
                    ])
                    ->visible(fn (BookingModel $record): bool => $record->status->isActive())
                    ->action(function (BookingModel $record, array $data): void {
                        $service = app(BookingServiceInterface::class);
                        $service->cancelBooking(new CancelBookingDTO(
                            bookingId: $record->id,
                            userId: (string) Auth::id(),
                            reason: $data['reason'],
                        ));

                        Notification::make()
                            ->title(__('venue-bookings::messages.notifications.booking_cancelled'))
                            ->success()
                            ->send();
                    }),

                Action::make('no_show')
                    ->label(__('venue-bookings::messages.actions.no_show'))
                    ->icon('heroicon-o-user-minus')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (BookingModel $record): bool => $record->status === BookingStatus::Confirmed)
                    ->action(function (BookingModel $record): void {
                        $service = app(BookingServiceInterface::class);
                        $service->markNoShow($record->id);

                        Notification::make()
                            ->title(__('venue-bookings::messages.notifications.booking_no_show'))
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
            ])
            ->defaultSort('date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
            'create' => Pages\CreateBooking::route('/create'),
            'edit' => Pages\EditBooking::route('/{record}/edit'),
        ];
    }
}
