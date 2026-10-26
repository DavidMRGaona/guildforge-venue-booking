<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Repositories;

use App\Application\Services\TimezoneServiceInterface;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Modules\VenueBookings\Domain\Entities\Booking;
use Modules\VenueBookings\Domain\Enums\BookingStatus;
use Modules\VenueBookings\Domain\Exceptions\BookingNotFoundException;
use Modules\VenueBookings\Domain\Repositories\BookingRepositoryInterface;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Modules\VenueBookings\Domain\ValueObjects\BookingFieldValue;
use Modules\VenueBookings\Domain\ValueObjects\BookingId;
use Modules\VenueBookings\Domain\ValueObjects\TimeRange;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Modules\VenueBookings\Infrastructure\Services\BookingSettingsReader;

final readonly class EloquentBookingRepository implements BookingRepositoryInterface
{
    public function save(Booking $booking): void
    {
        BookingModel::query()->updateOrCreate(
            ['id' => $booking->id->value],
            $this->toArray($booking),
        );
    }

    public function find(BookingId $id): ?Booking
    {
        $model = BookingModel::query()->find($id->value);

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function findOrFail(BookingId $id): Booking
    {
        $entity = $this->find($id);

        if (! $entity instanceof Booking) {
            throw BookingNotFoundException::withId($id->value);
        }

        return $entity;
    }

    public function delete(BookingId $id): void
    {
        BookingModel::query()->where('id', $id->value)->delete();
    }

    /**
     * @return array<Booking>
     */
    public function getByResourceAndDate(BookableResourceId $resourceId, string $date): array
    {
        return BookingModel::query()
            ->where('resource_id', $resourceId->value)
            ->where('date', $date)
            ->orderBy('start_time')
            ->get()
            ->map(fn (BookingModel $model): Booking => $this->toEntity($model))
            ->all();
    }

    /**
     * @return array<Booking>
     */
    public function getByUser(string $userId): array
    {
        return BookingModel::query()
            ->where('user_id', $userId)
            ->orderByDesc('date')
            ->orderBy('start_time')
            ->get()
            ->map(fn (BookingModel $model): Booking => $this->toEntity($model))
            ->all();
    }

    /**
     * @return array<Booking>
     */
    public function getByStatus(BookingStatus $status): array
    {
        return BookingModel::query()
            ->where('status', $status->value)
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->map(fn (BookingModel $model): Booking => $this->toEntity($model))
            ->all();
    }

    public function countActiveByUser(string $userId): int
    {
        return BookingModel::query()
            ->where('user_id', $userId)
            ->whereIn('status', [
                BookingStatus::Pending->value,
                BookingStatus::Confirmed->value,
            ])
            ->count();
    }

    public function hasOverlappingBooking(
        BookableResourceId $resourceId,
        string $date,
        TimeRange $timeRange,
        ?BookingId $excludeBookingId = null,
    ): bool {
        // A booking can only meet the day before (one crossing midnight into $date) and the day
        // after ($timeRange crossing midnight): narrow to those days in SQL, compare in PHP
        $bookings = $this->getByResourceInDateRange($resourceId, ...$this->neighbourDays($date));

        foreach ($bookings as $booking) {
            if ($excludeBookingId instanceof BookingId && $booking->id->equals($excludeBookingId)) {
                continue;
            }

            if ($booking->overlaps($date, $timeRange)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The days before and after a Y-m-d date; the date itself twice when it cannot be read.
     *
     * @return array{string, string}
     */
    private function neighbourDays(string $date): array
    {
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));

        if ($day === false) {
            return [$date, $date];
        }

        return [$day->modify('-1 day')->format('Y-m-d'), $day->modify('+1 day')->format('Y-m-d')];
    }

    /**
     * @return array<Booking>
     */
    public function getConfirmedBookingsForReminder(
        DateTimeInterface $from,
        DateTimeInterface $to,
    ): array {
        // Date and time live in the venue zone: read the window there, whatever zone it came in
        $timezone = app(BookingSettingsReader::class)->getDateTimeZone();
        $from = DateTimeImmutable::createFromInterface($from)->setTimezone($timezone);
        $to = DateTimeImmutable::createFromInterface($to)->setTimezone($timezone);

        // Narrow by calendar day in SQL, then compare the exact start in PHP
        // (date and time live in separate columns; this stays portable).
        $bookings = BookingModel::query()
            ->where('status', BookingStatus::Confirmed->value)
            ->whereNull('reminder_sent_at')
            ->whereBetween('date', [
                $from->format('Y-m-d'),
                $to->format('Y-m-d'),
            ])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->map(fn (BookingModel $model): Booking => $this->toEntity($model))
            ->all();

        return array_values(array_filter(
            $bookings,
            function (Booking $booking) use ($from, $to, $timezone): bool {
                $start = DateTimeImmutable::createFromFormat(
                    '!Y-m-d H:i',
                    $booking->date.' '.substr($booking->timeRange->startTime, 0, 5),
                    $timezone,
                );

                return $start !== false && $start >= $from && $start <= $to;
            },
        ));
    }

    public function markReminderSent(BookingId $id, DateTimeInterface $sentAt): void
    {
        $stored = app(TimezoneServiceInterface::class)->toStorage($sentAt);

        BookingModel::query()
            ->where('id', $id->value)
            ->update(['reminder_sent_at' => $stored->format('Y-m-d H:i:s')]);
    }

    /**
     * @return array<Booking>
     */
    public function getConfirmedEndedBy(DateTimeInterface $endedBy): array
    {
        $timezone = app(BookingSettingsReader::class)->getDateTimeZone();
        $endedBy = DateTimeImmutable::createFromInterface($endedBy)->setTimezone($timezone);

        // Narrow by status and calendar day in SQL (a booking ends on its day or the next),
        // then compare the exact end in PHP, as getConfirmedBookingsForReminder() does
        $bookings = BookingModel::query()
            ->where('status', BookingStatus::Confirmed->value)
            ->where('date', '<=', $endedBy->format('Y-m-d'))
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->map(fn (BookingModel $model): Booking => $this->toEntity($model))
            ->all();

        return array_values(array_filter(
            $bookings,
            fn (Booking $booking): bool => $booking->endsAt($timezone) <= $endedBy,
        ));
    }

    /**
     * @return array<Booking>
     */
    public function getByResourceInDateRange(
        BookableResourceId $resourceId,
        string $fromDate,
        string $toDate,
    ): array {
        return BookingModel::query()
            ->where('resource_id', $resourceId->value)
            ->whereBetween('date', [$fromDate, $toDate])
            ->whereIn('status', [
                BookingStatus::Pending->value,
                BookingStatus::Confirmed->value,
            ])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->map(fn (BookingModel $model): Booking => $this->toEntity($model))
            ->all();
    }

    private function toEntity(BookingModel $model): Booking
    {
        $fieldValues = $model->field_values !== null
            ? array_map(
                BookingFieldValue::fromArray(...),
                $model->field_values,
            )
            : [];

        return new Booking(
            id: BookingId::fromString($model->id),
            resourceId: BookableResourceId::fromString($model->resource_id),
            userId: $model->user_id,
            date: $model->date->format('Y-m-d'),
            timeRange: new TimeRange(
                startTime: $model->start_time,
                endTime: $model->end_time,
            ),
            status: $model->status,
            eventId: $model->event_id,
            gameTableId: $model->game_table_id,
            tournamentId: $model->tournament_id,
            campaignId: $model->campaign_id,
            fieldValues: $fieldValues,
            cancellationReason: $model->cancellation_reason,
            adminNotes: $model->admin_notes,
            confirmedAt: $model->confirmed_at !== null
                ? $model->confirmed_at->toDateTimeImmutable()
                : null,
            cancelledAt: $model->cancelled_at !== null
                ? $model->cancelled_at->toDateTimeImmutable()
                : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(Booking $booking): array
    {
        return [
            'id' => $booking->id->value,
            'resource_id' => $booking->resourceId->value,
            'user_id' => $booking->userId,
            'date' => $booking->date,
            'start_time' => $booking->timeRange->startTime,
            'end_time' => $booking->timeRange->endTime,
            'status' => $booking->status->value,
            'event_id' => $booking->eventId,
            'game_table_id' => $booking->gameTableId,
            'tournament_id' => $booking->tournamentId,
            'campaign_id' => $booking->campaignId,
            'field_values' => $booking->fieldValues !== []
                ? array_map(
                    fn (BookingFieldValue $fv): array => $fv->toArray(),
                    $booking->fieldValues,
                )
                : null,
            'cancellation_reason' => $booking->cancellationReason,
            'admin_notes' => $booking->adminNotes,
            'confirmed_at' => $booking->confirmedAt,
            'cancelled_at' => $booking->cancelledAt,
        ];
    }
}
