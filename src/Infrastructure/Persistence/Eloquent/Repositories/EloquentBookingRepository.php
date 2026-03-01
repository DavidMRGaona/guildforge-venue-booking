<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Repositories;

use DateTimeImmutable;
use DateTimeInterface;
use Modules\VenueBookings\Domain\Entities\Booking;
use Modules\VenueBookings\Domain\Enums\BookingStatus;
use Modules\VenueBookings\Domain\Exceptions\BookingNotFoundException;
use Modules\VenueBookings\Domain\Repositories\BookingRepositoryInterface;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Modules\VenueBookings\Domain\ValueObjects\BookingFieldValue;
use Modules\VenueBookings\Domain\ValueObjects\BookingId;
use Modules\VenueBookings\Domain\ValueObjects\TimeRange;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;

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

        if ($entity === null) {
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
        $query = BookingModel::query()
            ->where('resource_id', $resourceId->value)
            ->where('date', $date)
            ->whereIn('status', [
                BookingStatus::Pending->value,
                BookingStatus::Confirmed->value,
            ]);

        if ($timeRange->crossesMidnight()) {
            $query->where(function ($q) use ($timeRange): void {
                $q->where('start_time', '<', $timeRange->endTime)
                    ->orWhere('end_time', '>', $timeRange->startTime);
            });
        } else {
            $query->where('start_time', '<', $timeRange->endTime)
                ->where('end_time', '>', $timeRange->startTime);
        }

        if ($excludeBookingId !== null) {
            $query->where('id', '!=', $excludeBookingId->value);
        }

        return $query->exists();
    }

    /**
     * @return array<Booking>
     */
    public function getConfirmedBookingsForReminder(
        DateTimeInterface $from,
        DateTimeInterface $to,
    ): array {
        return BookingModel::query()
            ->where('status', BookingStatus::Confirmed->value)
            ->whereBetween('date', [
                $from->format('Y-m-d'),
                $to->format('Y-m-d'),
            ])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->map(fn (BookingModel $model): Booking => $this->toEntity($model))
            ->all();
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
                fn (array $data): BookingFieldValue => BookingFieldValue::fromArray($data),
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
                ? new DateTimeImmutable($model->confirmed_at->toDateTimeString())
                : null,
            cancelledAt: $model->cancelled_at !== null
                ? new DateTimeImmutable($model->cancelled_at->toDateTimeString())
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
            'field_values' => ! empty($booking->fieldValues)
                ? array_map(
                    fn (BookingFieldValue $fv): array => $fv->toArray(),
                    $booking->fieldValues,
                )
                : null,
            'cancellation_reason' => $booking->cancellationReason,
            'admin_notes' => $booking->adminNotes,
            'confirmed_at' => $booking->confirmedAt?->format('Y-m-d H:i:s'),
            'cancelled_at' => $booking->cancelledAt?->format('Y-m-d H:i:s'),
        ];
    }
}
