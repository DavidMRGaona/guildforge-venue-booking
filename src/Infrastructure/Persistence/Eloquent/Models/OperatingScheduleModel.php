<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function resource(): BelongsTo
    {
        return $this->belongsTo(BookableResourceModel::class, 'resource_id');
    }
}
