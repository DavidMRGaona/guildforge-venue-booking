<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $resource_id
 * @property string $scheduling_mode
 * @property int $slot_duration_minutes
 * @property int $min_consecutive_slots
 * @property int $max_consecutive_slots
 * @property array<int, array{day_of_week: int, open_time: string, close_time: string, is_enabled: bool, label?: string|null}> $day_schedules
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BookableResourceModel $resource
 */
final class OperatingScheduleModel extends Model
{
    use HasUuids;

    protected $table = 'venuebookings_operating_schedules';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'resource_id',
        'scheduling_mode',
        'slot_duration_minutes',
        'min_consecutive_slots',
        'max_consecutive_slots',
        'day_schedules',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'scheduling_mode' => 'string',
            'slot_duration_minutes' => 'integer',
            'min_consecutive_slots' => 'integer',
            'max_consecutive_slots' => 'integer',
            'day_schedules' => 'array',
        ];
    }

    /**
     * @return BelongsTo<BookableResourceModel, $this>
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(BookableResourceModel::class, 'resource_id');
    }
}
