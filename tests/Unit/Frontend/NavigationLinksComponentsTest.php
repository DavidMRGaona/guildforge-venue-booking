<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Unit\Frontend;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Source guards, not behaviour tests: the module has no Vue test runner. Every page can be reached
 * by clicking: the profile tab links the personal list and the calendar (also with no bookings
 * yet) and the personal list links back to the calendar.
 */
final class NavigationLinksComponentsTest extends TestCase
{
    private const string JS = __DIR__ . '/../../../resources/js/';

    public function test_the_profile_tab_links_the_personal_list_and_the_calendar_even_without_bookings(): void
    {
        $source = $this->source('components/profile/ProfileBookingsSection.vue');

        $this->assertStringContainsString("import { router, Link } from '@inertiajs/vue3';", $source);
        $this->assertSame(1, substr_count($source, 'href="/reservas/mis-reservas"'));
        $this->assertSame(1, substr_count($source, 'href="/reservas"'));
        $this->assertStringContainsString("t('venueBookings.profile.viewAll')", $source);
        $this->assertStringContainsString("t('booking_calendar.new_booking')", $source);

        // The links sit in the wrapper that always renders; only the sections depend on having bookings
        $this->assertStringContainsString('<template v-if="profileBookings && profileBookings.total > 0">', $source);
        $this->assertMatchesRegularExpression('/<template>\s*<div class="space-y-10">\s*<!--[^>]*-->\s*<div class="flex flex-wrap justify-end gap-3">\s*<Link\s+href="\/reservas\/mis-reservas"/', $source);
    }

    public function test_the_personal_list_links_back_to_the_calendar_from_its_header(): void
    {
        $source = $this->source('pages/VenueBookings/MyBookings.vue');

        $this->assertStringContainsString("import { router, Link } from '@inertiajs/vue3';", $source);
        $this->assertMatchesRegularExpression('/<h1[^>]*>.*?<\/h1>\s*<Link\s+href="\/reservas"/s', $source);
        $this->assertStringContainsString("t('booking_calendar.new_booking')", $source);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function newTexts(): array
    {
        return [
            'es' => ['locales/es.ts', 'es'],
            'en' => ['locales/en.ts', 'en'],
        ];
    }

    #[DataProvider('newTexts')]
    public function test_the_new_texts_exist_in_both_locales(string $file, string $locale): void
    {
        $source = $this->source($file);

        preg_match('/^ {8}profile: \{\n(.*?)^ {8}\},$/ms', $source, $profile);
        $this->assertMatchesRegularExpression('/^ {12}viewAll: /m', $profile[1] ?? '', "{$locale} profile.viewAll");

        preg_match('/^ {4}booking_calendar: \{\n(.*?)^ {4}\},$/ms', $source, $calendar);
        $this->assertMatchesRegularExpression('/^ {8}new_booking: /m', $calendar[1] ?? '', "{$locale} booking_calendar.new_booking");
    }

    private function source(string $file): string
    {
        $source = file_get_contents(self::JS . $file);
        $this->assertIsString($source);

        return $source;
    }
}
