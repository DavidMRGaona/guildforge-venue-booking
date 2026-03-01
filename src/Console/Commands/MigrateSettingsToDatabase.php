<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Console\Commands;

use App\Application\Services\SettingsServiceInterface;
use Illuminate\Console\Command;

final class MigrateSettingsToDatabase extends Command
{
    protected $signature = 'venue-bookings:migrate-settings';

    protected $description = 'Migrate venue-bookings settings from file-based config to the database';

    public function handle(SettingsServiceInterface $settingsService): int
    {
        $existing = $settingsService->get('module_settings:venue-bookings');

        if ($existing !== null && $existing !== '') {
            $this->info('Database settings already exist — skipping migration.');

            return Command::SUCCESS;
        }

        /** @var array<string, mixed> $settings */
        $settings = config('modules.settings.venue-bookings', []);

        if ($settings === []) {
            $this->warn('No file-based settings found to migrate.');

            return Command::SUCCESS;
        }

        $settingsService->set(
            'module_settings:venue-bookings',
            json_encode($settings, JSON_THROW_ON_ERROR),
        );

        $this->info('Settings migrated to database successfully.');

        return Command::SUCCESS;
    }
}
