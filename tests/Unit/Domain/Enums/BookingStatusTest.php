<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Unit\Domain\Enums;

use Modules\VenueBookings\Domain\Enums\BookingStatus;
use Tests\TestCase;

final class BookingStatusTest extends TestCase
{
    public function test_it_has_expected_cases(): void
    {
        $cases = BookingStatus::cases();

        $this->assertCount(6, $cases);
        $this->assertContains(BookingStatus::Pending, $cases);
        $this->assertContains(BookingStatus::Confirmed, $cases);
        $this->assertContains(BookingStatus::Completed, $cases);
        $this->assertContains(BookingStatus::Cancelled, $cases);
        $this->assertContains(BookingStatus::NoShow, $cases);
        $this->assertContains(BookingStatus::Rejected, $cases);
    }

    public function test_it_has_correct_values(): void
    {
        $this->assertEquals('pending', BookingStatus::Pending->value);
        $this->assertEquals('confirmed', BookingStatus::Confirmed->value);
        $this->assertEquals('completed', BookingStatus::Completed->value);
        $this->assertEquals('cancelled', BookingStatus::Cancelled->value);
        $this->assertEquals('no_show', BookingStatus::NoShow->value);
        $this->assertEquals('rejected', BookingStatus::Rejected->value);
    }

    public function test_it_returns_label_for_each_case(): void
    {
        foreach (BookingStatus::cases() as $case) {
            $this->assertIsString($case->label());
            $this->assertNotEmpty($case->label());
        }
    }

    public function test_it_returns_color_for_each_case(): void
    {
        foreach (BookingStatus::cases() as $case) {
            $this->assertIsString($case->color());
            $this->assertNotEmpty($case->color());
        }
    }

    public function test_it_returns_options_array(): void
    {
        $options = BookingStatus::options();

        $this->assertIsArray($options);
        $this->assertCount(6, $options);
        $this->assertArrayHasKey('pending', $options);
        $this->assertArrayHasKey('confirmed', $options);
        $this->assertArrayHasKey('completed', $options);
        $this->assertArrayHasKey('cancelled', $options);
        $this->assertArrayHasKey('no_show', $options);
        $this->assertArrayHasKey('rejected', $options);
    }

    public function test_it_returns_values_array(): void
    {
        $values = BookingStatus::values();

        $this->assertIsArray($values);
        $this->assertCount(6, $values);
        $this->assertContains('pending', $values);
        $this->assertContains('confirmed', $values);
        $this->assertContains('completed', $values);
        $this->assertContains('cancelled', $values);
        $this->assertContains('no_show', $values);
        $this->assertContains('rejected', $values);
    }

    public function test_it_identifies_final_statuses(): void
    {
        $this->assertTrue(BookingStatus::Completed->isFinal());
        $this->assertTrue(BookingStatus::Cancelled->isFinal());
        $this->assertTrue(BookingStatus::NoShow->isFinal());
        $this->assertTrue(BookingStatus::Rejected->isFinal());

        $this->assertFalse(BookingStatus::Pending->isFinal());
        $this->assertFalse(BookingStatus::Confirmed->isFinal());
    }

    public function test_it_identifies_active_statuses(): void
    {
        $this->assertTrue(BookingStatus::Pending->isActive());
        $this->assertTrue(BookingStatus::Confirmed->isActive());

        $this->assertFalse(BookingStatus::Completed->isActive());
        $this->assertFalse(BookingStatus::Cancelled->isActive());
        $this->assertFalse(BookingStatus::NoShow->isActive());
        $this->assertFalse(BookingStatus::Rejected->isActive());
    }

    public function test_can_transition_from_pending_to_confirmed(): void
    {
        $this->assertTrue(BookingStatus::Pending->canTransitionTo(BookingStatus::Confirmed));
    }

    public function test_can_transition_from_pending_to_rejected(): void
    {
        $this->assertTrue(BookingStatus::Pending->canTransitionTo(BookingStatus::Rejected));
    }

    public function test_can_transition_from_pending_to_cancelled(): void
    {
        $this->assertTrue(BookingStatus::Pending->canTransitionTo(BookingStatus::Cancelled));
    }

    public function test_can_transition_from_confirmed_to_completed(): void
    {
        $this->assertTrue(BookingStatus::Confirmed->canTransitionTo(BookingStatus::Completed));
    }

    public function test_can_transition_from_confirmed_to_cancelled(): void
    {
        $this->assertTrue(BookingStatus::Confirmed->canTransitionTo(BookingStatus::Cancelled));
    }

    public function test_can_transition_from_confirmed_to_no_show(): void
    {
        $this->assertTrue(BookingStatus::Confirmed->canTransitionTo(BookingStatus::NoShow));
    }

    public function test_cannot_transition_from_completed(): void
    {
        foreach (BookingStatus::cases() as $target) {
            if ($target === BookingStatus::Completed) {
                continue;
            }

            $this->assertFalse(
                BookingStatus::Completed->canTransitionTo($target),
                "Should not be able to transition from Completed to {$target->value}"
            );
        }
    }

    public function test_it_returns_css_color_for_each_case(): void
    {
        $expected = [
            [BookingStatus::Pending, 'var(--color-warning)'],
            [BookingStatus::Confirmed, 'var(--color-success)'],
            [BookingStatus::Completed, 'var(--color-secondary)'],
            [BookingStatus::Cancelled, 'var(--color-error)'],
            [BookingStatus::NoShow, 'var(--color-error)'],
            [BookingStatus::Rejected, 'var(--color-error)'],
        ];

        foreach ($expected as [$status, $color]) {
            $this->assertSame($color, $status->calendarCssColor());
        }
    }

    public function test_cannot_transition_from_cancelled(): void
    {
        foreach (BookingStatus::cases() as $target) {
            if ($target === BookingStatus::Cancelled) {
                continue;
            }

            $this->assertFalse(
                BookingStatus::Cancelled->canTransitionTo($target),
                "Should not be able to transition from Cancelled to {$target->value}"
            );
        }
    }
}
