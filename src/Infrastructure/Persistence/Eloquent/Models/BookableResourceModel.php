<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\VenueBookings\Domain\Enums\BookableResourceStatus;

final class BookableResourceModel extends Model
{
    use HasSlug;
    use HasUuids;

    protected $table = 'venuebookings_resources';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'slug',
        'description',
        'capacity',
        'status',
        'sort_order',
        'field_config',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'status' => BookableResourceStatus::class,
            'sort_order' => 'integer',
            'field_config' => 'array',
        ];
    }

    public function operatingSchedule(): HasOne
    {
        return $this->hasOne(OperatingScheduleModel::class, 'resource_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(BookingModel::class, 'resource_id');
    }

    public function getSlugEntityType(): string
    {
        return 'bookable_resource';
    }

    protected function getSlugSourceField(): string
    {
        return 'name';
    }
}
