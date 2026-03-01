<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Filament\Widgets;

use App\Application\Services\DashboardWidgetConfigServiceInterface;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Carbon;
use Modules\VenueBookings\Domain\Enums\BookingStatus;
use Modules\VenueBookings\Filament\Resources\BookingResource;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;

final class UpcomingBookingsWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 12;

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('venue-bookings::messages.widgets.upcoming_bookings.title'))
            ->query(
                BookingModel::query()
                    ->where('date', '>=', Carbon::today()->toDateString())
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

    public function getTableRecordUrl(BookingModel $record): ?string
    {
        return BookingResource::getUrl('edit', ['record' => $record->id]);
    }
}
