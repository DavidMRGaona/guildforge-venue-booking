<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A time of day as `HH:MM`. PostgreSQL returns time columns with seconds
 * (`17:00:00`) while SQLite returns what was written, so both are trimmed here.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
final class TimeOfDay implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $this->toHoursAndMinutes($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $this->toHoursAndMinutes($value);
    }

    private function toHoursAndMinutes(mixed $value): ?string
    {
        return $value === null ? null : substr((string) $value, 0, 5);
    }
}
