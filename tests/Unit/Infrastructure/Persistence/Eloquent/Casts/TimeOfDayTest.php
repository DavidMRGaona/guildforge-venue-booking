<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Unit\Infrastructure\Persistence\Eloquent\Casts;

use Illuminate\Database\Eloquent\Model;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Casts\TimeOfDay;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TimeOfDayTest extends TestCase
{
    /**
     * @return array<string, array{mixed, string|null}>
     */
    public static function values(): array
    {
        return [
            'hours and minutes' => ['17:00', '17:00'],
            'with seconds' => ['17:00:00', '17:00'],
            'with fractional seconds' => ['08:15:30.000000', '08:15'],
            'null' => [null, null],
        ];
    }

    #[DataProvider('values')]
    public function test_it_reads_hours_and_minutes(mixed $value, ?string $expected): void
    {
        $this->assertSame($expected, (new TimeOfDay)->get($this->model(), 'start_time', $value, []));
    }

    #[DataProvider('values')]
    public function test_it_writes_hours_and_minutes(mixed $value, ?string $expected): void
    {
        $this->assertSame($expected, (new TimeOfDay)->set($this->model(), 'start_time', $value, []));
    }

    private function model(): Model
    {
        return new class extends Model {};
    }
}
