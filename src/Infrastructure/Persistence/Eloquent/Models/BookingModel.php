<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\VenueBookings\Domain\Enums\BookingStatus;

final class BookingModel extends Model
{
    use HasUuids;

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
        static::updating(function (BookingModel $booking): void {
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

    public function resource(): BelongsTo
    {
        return $this->belongsTo(BookableResourceModel::class, 'resource_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}
