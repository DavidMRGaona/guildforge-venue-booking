<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Modules\VenueBookings\Domain\Enums\BookingStatus;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;

final class TodayBookingsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected function getColumns(): int
    {
        return 2;
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $today = Carbon::today();

        $todayBookingsCount = BookingModel::query()
            ->where('date', $today->toDateString())
            ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::Pending->value])
            ->count();

        $pendingApprovalsCount = BookingModel::query()
            ->where('status', BookingStatus::Pending->value)
            ->count();

        return [
            Stat::make(
                __('venue-bookings::messages.widgets.today_bookings.today_count'),
                (string) $todayBookingsCount,
            )
                ->description(__('venue-bookings::messages.widgets.today_bookings.today_description'))
                ->icon('heroicon-o-calendar-days')
                ->color('success'),

            Stat::make(
                __('venue-bookings::messages.widgets.today_bookings.pending_count'),
                (string) $pendingApprovalsCount,
            )
                ->description(__('venue-bookings::messages.widgets.today_bookings.pending_description'))
                ->icon('heroicon-o-clock')
                ->color($pendingApprovalsCount > 0 ? 'warning' : 'gray'),
        ];
    }
}
