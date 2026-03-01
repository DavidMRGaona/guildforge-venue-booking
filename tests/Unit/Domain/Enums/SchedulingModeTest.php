<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Unit\Domain\Enums;

use Modules\VenueBookings\Domain\Enums\SchedulingMode;
use Tests\TestCase;

final class SchedulingModeTest extends TestCase
{
    public function test_it_has_expected_cases(): void
    {
        $cases = SchedulingMode::cases();

        $this->assertCount(2, $cases);
        $this->assertContains(SchedulingMode::TimeSlots, $cases);
        $this->assertContains(SchedulingMode::TimeBlocks, $cases);
    }

    public function test_it_has_correct_values(): void
    {
        $this->assertEquals('time_slots', SchedulingMode::TimeSlots->value);
        $this->assertEquals('time_blocks', SchedulingMode::TimeBlocks->value);
    }

    public function test_it_returns_label_for_each_case(): void
    {
        foreach (SchedulingMode::cases() as $case) {
            $this->assertIsString($case->label());
            $this->assertNotEmpty($case->label());
        }
    }

    public function test_it_returns_options_array(): void
    {
        $options = SchedulingMode::options();

        $this->assertIsArray($options);
        $this->assertCount(2, $options);
        $this->assertArrayHasKey('time_slots', $options);
        $this->assertArrayHasKey('time_blocks', $options);
    }
}
