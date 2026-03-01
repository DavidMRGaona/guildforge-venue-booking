<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Unit\Domain\ValueObjects;

use Modules\VenueBookings\Domain\ValueObjects\BookingSlot;
use Tests\TestCase;

final class BookingSlotTest extends TestCase
{
    public function test_it_creates_available_slot(): void
    {
        $slot = new BookingSlot(
            startTime: '10:00',
            endTime: '11:00',
            isAvailable: true,
        );

        $this->assertEquals('10:00', $slot->startTime);
        $this->assertEquals('11:00', $slot->endTime);
        $this->assertTrue($slot->isAvailable);
    }

    public function test_it_creates_unavailable_slot(): void
    {
        $slot = new BookingSlot(
            startTime: '14:00',
            endTime: '15:00',
            isAvailable: false,
        );

        $this->assertEquals('14:00', $slot->startTime);
        $this->assertEquals('15:00', $slot->endTime);
        $this->assertFalse($slot->isAvailable);
    }

    public function test_it_serializes_to_array(): void
    {
        $slot = new BookingSlot(
            startTime: '10:00',
            endTime: '11:00',
            isAvailable: true,
        );

        $array = $slot->toArray();

        $this->assertEquals([
            'start_time' => '10:00',
            'end_time' => '11:00',
            'is_available' => true,
            'label' => null,
        ], $array);
    }

    public function test_it_creates_slot_with_label(): void
    {
        $slot = new BookingSlot(
            startTime: '10:00',
            endTime: '14:00',
            isAvailable: true,
            label: 'Mañana',
        );

        $this->assertEquals('Mañana', $slot->label);
    }

    public function test_label_defaults_to_null(): void
    {
        $slot = new BookingSlot(
            startTime: '10:00',
            endTime: '11:00',
            isAvailable: true,
        );

        $this->assertNull($slot->label);
    }

    public function test_to_array_includes_label(): void
    {
        $slot = new BookingSlot(
            startTime: '10:00',
            endTime: '14:00',
            isAvailable: true,
            label: 'Mañana',
        );

        $array = $slot->toArray();

        $this->assertEquals([
            'start_time' => '10:00',
            'end_time' => '14:00',
            'is_available' => true,
            'label' => 'Mañana',
        ], $array);
    }
}
