<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Unit\Domain\Enums;

use Modules\VenueBookings\Domain\Enums\FieldVisibility;
use Tests\TestCase;

final class FieldVisibilityTest extends TestCase
{
    public function test_it_has_expected_cases(): void
    {
        $cases = FieldVisibility::cases();

        $this->assertCount(4, $cases);
        $this->assertContains(FieldVisibility::Public, $cases);
        $this->assertContains(FieldVisibility::Authenticated, $cases);
        $this->assertContains(FieldVisibility::Permission, $cases);
        $this->assertContains(FieldVisibility::AdminOnly, $cases);
    }

    public function test_it_has_correct_values(): void
    {
        $this->assertEquals('public', FieldVisibility::Public->value);
        $this->assertEquals('authenticated', FieldVisibility::Authenticated->value);
        $this->assertEquals('permission', FieldVisibility::Permission->value);
        $this->assertEquals('admin_only', FieldVisibility::AdminOnly->value);
    }

    public function test_it_returns_label_for_each_case(): void
    {
        foreach (FieldVisibility::cases() as $case) {
            $this->assertIsString($case->label());
            $this->assertNotEmpty($case->label());
        }
    }

    public function test_it_returns_options_array(): void
    {
        $options = FieldVisibility::options();

        $this->assertIsArray($options);
        $this->assertCount(4, $options);
        $this->assertArrayHasKey('public', $options);
        $this->assertArrayHasKey('authenticated', $options);
        $this->assertArrayHasKey('permission', $options);
        $this->assertArrayHasKey('admin_only', $options);
    }

    public function test_it_has_correct_ordering(): void
    {
        $publicLevel = FieldVisibility::Public->level();
        $authenticatedLevel = FieldVisibility::Authenticated->level();
        $permissionLevel = FieldVisibility::Permission->level();
        $adminOnlyLevel = FieldVisibility::AdminOnly->level();

        $this->assertIsInt($publicLevel);
        $this->assertIsInt($authenticatedLevel);
        $this->assertIsInt($permissionLevel);
        $this->assertIsInt($adminOnlyLevel);

        $this->assertLessThan($authenticatedLevel, $publicLevel);
        $this->assertLessThan($permissionLevel, $authenticatedLevel);
        $this->assertLessThan($adminOnlyLevel, $permissionLevel);
    }

    public function test_public_is_visible_to_everyone(): void
    {
        $this->assertTrue(FieldVisibility::Public->isVisibleToEveryone());

        $this->assertFalse(FieldVisibility::Authenticated->isVisibleToEveryone());
        $this->assertFalse(FieldVisibility::Permission->isVisibleToEveryone());
        $this->assertFalse(FieldVisibility::AdminOnly->isVisibleToEveryone());
    }
}
