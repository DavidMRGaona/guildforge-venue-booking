<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasSlug;
use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Modules\VenueBookings\Domain\Enums\BookableResourceStatus;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int|null $capacity
 * @property BookableResourceStatus $status
 * @property int $sort_order
 * @property array<string, mixed>|null $field_config
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read OperatingScheduleModel|null $operatingSchedule
 * @property-read Collection<int, BookingModel> $bookings
 */
final class BookableResourceModel extends Model
{
    use HasSlug;
    use HasUuids;
    use StoresInstantsInStorageTimezone;

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

    /**
     * @return HasOne<OperatingScheduleModel, $this>
     */
    public function operatingSchedule(): HasOne
    {
        return $this->hasOne(OperatingScheduleModel::class, 'resource_id');
    }

    /**
     * @return HasMany<BookingModel, $this>
     */
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
