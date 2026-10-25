<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Filament\Widgets;

use App\Application\Services\DashboardWidgetConfigServiceInterface;
use Carbon\CarbonImmutable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Modules\VenueBookings\Domain\Enums\BookingStatus;
use Modules\VenueBookings\Filament\Resources\BookingResource;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Modules\VenueBookings\Infrastructure\Services\BookingSettingsReader;

final class UpcomingBookingsWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 12;

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', BookingModel::class) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('venue-bookings::messages.widgets.upcoming_bookings.title'))
            ->query(
                BookingModel::query()
                    ->where(fn (Builder $query) => $this->notEnded($query))
                    ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::Pending->value])
                    ->with(['resource', 'user'])
                    ->orderBy('date', 'asc')
                    ->orderBy('start_time', 'asc')
            )
            ->columns([
                TextColumn::make('resource.name')
                    ->label(__('venue-bookings::messages.fields.resource'))
                    ->limit(30),

                TextColumn::make('user.name')
                    ->label(__('venue-bookings::messages.fields.user'))
                    ->limit(20),

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
                    ->formatStateUsing(fn (BookingStatus $state): string => $state->label()),
            ])
            // As in the bookings list: the row opens the booking for whoever may edit it
            ->recordUrl(fn (BookingModel $record): ?string => BookingResource::canEdit($record)
                ? BookingResource::getUrl('edit', ['record' => $record])
                : null)
            ->emptyStateHeading(__('venue-bookings::messages.widgets.upcoming_bookings.no_upcoming'))
            ->emptyStateIcon('heroicon-o-calendar')
            ->paginated([$this->getConfiguredLimit()])
            ->defaultPaginationPageOption($this->getConfiguredLimit());
    }

    private function getConfiguredLimit(): int
    {
        return app(DashboardWidgetConfigServiceInterface::class)
            ->getLimit(static::class, 5);
    }

    /**
     * Bookings that have not ended by the venue's clock. Dates and times are venue wall clock;
     * a range whose end is before its start ends on the next day, so one from yesterday may
     * still be under way.
     *
     * @param  Builder<BookingModel>  $query
     */
    private function notEnded(Builder $query): void
    {
        $now = CarbonImmutable::now(app(BookingSettingsReader::class)->getDateTimeZone());
        $today = $now->toDateString();
        $time = $now->format('H:i:s');

        $query
            ->whereDate('date', '>', $today)
            ->orWhere(fn (Builder $todays) => $todays
                ->whereDate('date', $today)
                ->where(fn (Builder $unfinished) => $unfinished
                    ->where('end_time', '>', $time)
                    ->orWhereColumn('end_time', '<', 'start_time')))
            ->orWhere(fn (Builder $overnight) => $overnight
                ->whereDate('date', $now->subDay()->toDateString())
                ->whereColumn('end_time', '<', 'start_time')
                ->where('end_time', '>', $time));
    }
}
