<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Integration\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Tests\TestCase;

final class BookingModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reads_times_with_seconds_as_hours_and_minutes(): void
    {
        // PostgreSQL returns time columns with seconds; SQLite keeps what was written
        $resource = BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Room',
            'slug' => 'test-room',
            'status' => 'active',
            'sort_order' => 0,
        ]);
        $id = (string) Str::uuid();
        DB::table('venuebookings_bookings')->insert([
            'id' => $id,
            'resource_id' => $resource->id,
            'user_id' => UserModel::factory()->create()->id,
            'date' => '2026-03-15',
            'start_time' => '17:00:00',
            'end_time' => '19:30:00',
            'status' => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $booking = BookingModel::query()->findOrFail($id);

        $this->assertSame('17:00', $booking->start_time);
        $this->assertSame('19:30', $booking->end_time);
        $this->assertSame('17:00', $booking->getOriginal('start_time'));
        $this->assertSame(['start_time' => '17:00', 'end_time' => '19:30'], $booking->only(['start_time', 'end_time']));
    }
}
