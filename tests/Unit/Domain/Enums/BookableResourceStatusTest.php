<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Unit\Domain\Enums;

use Modules\VenueBookings\Domain\Enums\BookableResourceStatus;
use Tests\TestCase;

final class BookableResourceStatusTest extends TestCase
{
    public function test_it_has_expected_cases(): void
    {
        $cases = BookableResourceStatus::cases();

        $this->assertCount(3, $cases);
        $this->assertContains(BookableResourceStatus::Active, $cases);
        $this->assertContains(BookableResourceStatus::Inactive, $cases);
        $this->assertContains(BookableResourceStatus::Maintenance, $cases);
    }

    public function test_it_has_correct_values(): void
    {
        $this->assertEquals('active', BookableResourceStatus::Active->value);
        $this->assertEquals('inactive', BookableResourceStatus::Inactive->value);
        $this->assertEquals('maintenance', BookableResourceStatus::Maintenance->value);
    }

    public function test_it_returns_label_for_each_case(): void
    {
        foreach (BookableResourceStatus::cases() as $case) {
            $this->assertIsString($case->label());
            $this->assertNotEmpty($case->label());
        }
    }

    public function test_it_returns_color_for_each_case(): void
    {
        foreach (BookableResourceStatus::cases() as $case) {
            $this->assertIsString($case->color());
            $this->assertNotEmpty($case->color());
        }
    }

    public function test_it_returns_options_array(): void
    {
        $options = BookableResourceStatus::options();

        $this->assertIsArray($options);
        $this->assertCount(3, $options);
        $this->assertArrayHasKey('active', $options);
        $this->assertArrayHasKey('inactive', $options);
        $this->assertArrayHasKey('maintenance', $options);
    }

    public function test_it_identifies_bookable_status(): void
    {
        $this->assertTrue(BookableResourceStatus::Active->isBookable());

        $this->assertFalse(BookableResourceStatus::Inactive->isBookable());
        $this->assertFalse(BookableResourceStatus::Maintenance->isBookable());
    }
}
