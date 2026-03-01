<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Unit\Domain\Enums;

use Modules\VenueBookings\Domain\Enums\BookingFieldType;
use Tests\TestCase;

final class BookingFieldTypeTest extends TestCase
{
    public function test_it_has_expected_cases(): void
    {
        $cases = BookingFieldType::cases();

        $this->assertCount(5, $cases);
        $this->assertContains(BookingFieldType::Text, $cases);
        $this->assertContains(BookingFieldType::Textarea, $cases);
        $this->assertContains(BookingFieldType::Number, $cases);
        $this->assertContains(BookingFieldType::Select, $cases);
        $this->assertContains(BookingFieldType::Toggle, $cases);
    }

    public function test_it_has_correct_values(): void
    {
        $this->assertEquals('text', BookingFieldType::Text->value);
        $this->assertEquals('textarea', BookingFieldType::Textarea->value);
        $this->assertEquals('number', BookingFieldType::Number->value);
        $this->assertEquals('select', BookingFieldType::Select->value);
        $this->assertEquals('toggle', BookingFieldType::Toggle->value);
    }

    public function test_it_returns_label_for_each_case(): void
    {
        foreach (BookingFieldType::cases() as $case) {
            $this->assertIsString($case->label());
            $this->assertNotEmpty($case->label());
        }
    }

    public function test_it_returns_options_array(): void
    {
        $options = BookingFieldType::options();

        $this->assertIsArray($options);
        $this->assertCount(5, $options);
        $this->assertArrayHasKey('text', $options);
        $this->assertArrayHasKey('textarea', $options);
        $this->assertArrayHasKey('number', $options);
        $this->assertArrayHasKey('select', $options);
        $this->assertArrayHasKey('toggle', $options);
    }
}
