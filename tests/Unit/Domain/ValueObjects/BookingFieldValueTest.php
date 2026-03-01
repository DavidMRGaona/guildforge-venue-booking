<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Unit\Domain\ValueObjects;

use Modules\VenueBookings\Domain\ValueObjects\BookingFieldValue;
use Tests\TestCase;

final class BookingFieldValueTest extends TestCase
{
    public function test_it_creates_with_valid_data(): void
    {
        $fieldValue = new BookingFieldValue(
            fieldKey: 'player_count',
            fieldLabel: 'Number of players',
            value: 4,
            visibility: 'public',
        );

        $this->assertEquals('player_count', $fieldValue->fieldKey);
        $this->assertEquals('Number of players', $fieldValue->fieldLabel);
        $this->assertEquals(4, $fieldValue->value);
        $this->assertEquals('public', $fieldValue->visibility);
    }

    public function test_it_serializes_to_array(): void
    {
        $fieldValue = new BookingFieldValue(
            fieldKey: 'game_system',
            fieldLabel: 'Game system',
            value: 'Warhammer 40K',
            visibility: 'authenticated',
        );

        $array = $fieldValue->toArray();

        $this->assertEquals([
            'field_key' => 'game_system',
            'field_label' => 'Game system',
            'value' => 'Warhammer 40K',
            'visibility' => 'authenticated',
        ], $array);
    }

    public function test_it_creates_from_array(): void
    {
        $fieldValue = BookingFieldValue::fromArray([
            'field_key' => 'notes',
            'field_label' => 'Additional notes',
            'value' => 'Bring your own miniatures',
            'visibility' => 'public',
        ]);

        $this->assertEquals('notes', $fieldValue->fieldKey);
        $this->assertEquals('Additional notes', $fieldValue->fieldLabel);
        $this->assertEquals('Bring your own miniatures', $fieldValue->value);
        $this->assertEquals('public', $fieldValue->visibility);
    }

    public function test_it_handles_null_value(): void
    {
        $fieldValue = new BookingFieldValue(
            fieldKey: 'optional_field',
            fieldLabel: 'Optional field',
            value: null,
            visibility: 'admin_only',
        );

        $this->assertNull($fieldValue->value);
        $this->assertEquals('optional_field', $fieldValue->fieldKey);
    }
}
