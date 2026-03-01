<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Unit\Domain\ValueObjects;

use Modules\VenueBookings\Domain\ValueObjects\BookingFieldConfig;
use Tests\TestCase;

final class BookingFieldConfigTest extends TestCase
{
    public function test_it_creates_with_valid_data(): void
    {
        $config = new BookingFieldConfig(
            key: 'player_count',
            label: 'Number of players',
            type: 'number',
            required: true,
            visibility: 'public',
        );

        $this->assertEquals('player_count', $config->key);
        $this->assertEquals('Number of players', $config->label);
        $this->assertEquals('number', $config->type);
        $this->assertTrue($config->required);
        $this->assertEquals('public', $config->visibility);
        $this->assertNull($config->options);
    }

    public function test_it_creates_select_field_with_options(): void
    {
        $options = ['warhammer_40k', 'age_of_sigmar', 'dnd_5e'];

        $config = new BookingFieldConfig(
            key: 'game_system',
            label: 'Game system',
            type: 'select',
            required: true,
            visibility: 'public',
            options: $options,
        );

        $this->assertEquals('select', $config->type);
        $this->assertEquals($options, $config->options);
    }

    public function test_it_identifies_select_field(): void
    {
        $selectConfig = new BookingFieldConfig(
            key: 'game_system',
            label: 'Game system',
            type: 'select',
            required: true,
            visibility: 'public',
            options: ['option1', 'option2'],
        );

        $textConfig = new BookingFieldConfig(
            key: 'notes',
            label: 'Notes',
            type: 'text',
            required: false,
            visibility: 'public',
        );

        $this->assertTrue($selectConfig->isSelectField());
        $this->assertFalse($textConfig->isSelectField());
    }

    public function test_it_serializes_to_array(): void
    {
        $config = new BookingFieldConfig(
            key: 'player_count',
            label: 'Number of players',
            type: 'number',
            required: true,
            visibility: 'authenticated',
            options: null,
        );

        $array = $config->toArray();

        $this->assertEquals([
            'key' => 'player_count',
            'label' => 'Number of players',
            'type' => 'number',
            'required' => true,
            'visibility' => 'authenticated',
            'options' => null,
        ], $array);
    }

    public function test_it_creates_from_array(): void
    {
        $config = BookingFieldConfig::fromArray([
            'key' => 'game_system',
            'label' => 'Game system',
            'type' => 'select',
            'required' => true,
            'visibility' => 'public',
            'options' => ['warhammer_40k', 'dnd_5e'],
        ]);

        $this->assertEquals('game_system', $config->key);
        $this->assertEquals('Game system', $config->label);
        $this->assertEquals('select', $config->type);
        $this->assertTrue($config->required);
        $this->assertEquals('public', $config->visibility);
        $this->assertEquals(['warhammer_40k', 'dnd_5e'], $config->options);
    }

    public function test_it_defaults_options_to_null(): void
    {
        $config = new BookingFieldConfig(
            key: 'notes',
            label: 'Additional notes',
            type: 'textarea',
            required: false,
            visibility: 'admin_only',
        );

        $this->assertNull($config->options);
    }
}
