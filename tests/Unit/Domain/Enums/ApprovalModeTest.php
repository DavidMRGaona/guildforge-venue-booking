<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Unit\Domain\Enums;

use Modules\VenueBookings\Domain\Enums\ApprovalMode;
use Tests\TestCase;

final class ApprovalModeTest extends TestCase
{
    public function test_it_has_expected_cases(): void
    {
        $cases = ApprovalMode::cases();

        $this->assertCount(2, $cases);
        $this->assertContains(ApprovalMode::AutoConfirm, $cases);
        $this->assertContains(ApprovalMode::RequireApproval, $cases);
    }

    public function test_it_has_correct_values(): void
    {
        $this->assertEquals('auto_confirm', ApprovalMode::AutoConfirm->value);
        $this->assertEquals('require_approval', ApprovalMode::RequireApproval->value);
    }

    public function test_it_returns_label_for_each_case(): void
    {
        foreach (ApprovalMode::cases() as $case) {
            $this->assertIsString($case->label());
            $this->assertNotEmpty($case->label());
        }
    }

    public function test_it_returns_options_array(): void
    {
        $options = ApprovalMode::options();

        $this->assertIsArray($options);
        $this->assertCount(2, $options);
        $this->assertArrayHasKey('auto_confirm', $options);
        $this->assertArrayHasKey('require_approval', $options);
    }

    public function test_auto_confirm_does_not_require_approval(): void
    {
        $this->assertFalse(ApprovalMode::AutoConfirm->requiresApproval());
    }

    public function test_require_approval_requires_approval(): void
    {
        $this->assertTrue(ApprovalMode::RequireApproval->requiresApproval());
    }
}
