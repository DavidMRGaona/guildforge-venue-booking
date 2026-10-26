<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\VenueBookings\Domain\Enums\BookingStatus;

/**
 * @property string $id
 * @property string $resource_id
 * @property string $user_id
 * @property Carbon $date
 * @property string $start_time
 * @property string $end_time
 * @property BookingStatus $status
 * @property string|null $event_id
 * @property string|null $game_table_id
 * @property string|null $tournament_id
 * @property string|null $campaign_id
 * @property array<int, array{field_key: string, field_label: string, value: mixed, visibility: string}>|null $field_values
 * @property string|null $cancellation_reason
 * @property string|null $admin_notes
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $reminder_sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BookableResourceModel $resource
 * @property-read UserModel $user
 */
final class BookingModel extends Model
{
    use HasUuids;
    use StoresInstantsInStorageTimezone;

    protected $table = 'venuebookings_bookings';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'resource_id',
        'user_id',
        'date',
        'start_time',
        'end_time',
        'status',
        'event_id',
        'game_table_id',
        'tournament_id',
        'campaign_id',
        'field_values',
        'cancellation_reason',
        'admin_notes',
        'confirmed_at',
        'cancelled_at',
        'reminder_sent_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'status' => BookingStatus::class,
            'field_values' => 'array',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // A reminder belongs to a date and start time: once a booking is moved,
        // it is due for a new one.
        self::updating(function (BookingModel $booking): void {
            if ($booking->isDirty('reminder_sent_at')) {
                return;
            }

            $startTimeChanged = substr((string) $booking->getOriginal('start_time'), 0, 5)
                !== substr((string) $booking->getAttribute('start_time'), 0, 5);

            if ($booking->isDirty('date') || $startTimeChanged) {
                $booking->setAttribute('reminder_sent_at', null);
            }
        });
    }

    /**
     * @return BelongsTo<BookableResourceModel, $this>
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(BookableResourceModel::class, 'resource_id');
    }

    /**
     * @return BelongsTo<UserModel, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}
