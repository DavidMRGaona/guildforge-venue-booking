<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Notifications;

use App\Application\Mail\Contracts\HasMailCategory;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Mail\Services\MailPreferenceServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Modules\VenueBookings\Notifications\BookingCancelledNotification;
use Modules\VenueBookings\Notifications\BookingConfirmedNotification;
use Modules\VenueBookings\Notifications\BookingCreatedNotification;
use Modules\VenueBookings\Notifications\BookingMovedNotification;
use Modules\VenueBookings\Notifications\BookingReceivedNotification;
use Modules\VenueBookings\Notifications\BookingRejectedNotification;
use Modules\VenueBookings\Notifications\BookingReminderNotification;
use Modules\VenueBookings\VenueBookingsServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Every mail of the module belongs to a registered category, so people can choose what they receive.
 */
final class MailCategoriesTest extends ModuleTestCase
{
    use CreatesUsersWithPermissions;

    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    /**
     * @return array<string, array{0: Notification, 1: string}>
     */
    public static function notifications(): array
    {
        $args = ['b1', 'John', 'Sala', '2026-03-02', '10:00', '12:00'];

        return [
            'received' => [new BookingReceivedNotification(...$args), VenueBookingsServiceProvider::MAIL_MY_BOOKINGS],
            'confirmed' => [new BookingConfirmedNotification(...$args), VenueBookingsServiceProvider::MAIL_MY_BOOKINGS],
            'rejected' => [new BookingRejectedNotification(...$args), VenueBookingsServiceProvider::MAIL_MY_BOOKINGS],
            'cancelled' => [new BookingCancelledNotification(...$args), VenueBookingsServiceProvider::MAIL_MY_BOOKINGS],
            'moved' => [new BookingMovedNotification(...$args, ...['Sala', '2026-03-01', '10:00', '12:00']), VenueBookingsServiceProvider::MAIL_MY_BOOKINGS],
            'reminder' => [new BookingReminderNotification(...$args), VenueBookingsServiceProvider::MAIL_REMINDERS],
            'created' => [new BookingCreatedNotification(...$args), VenueBookingsServiceProvider::MAIL_MANAGEMENT],
        ];
    }

    #[DataProvider('notifications')]
    public function test_every_notification_has_a_registered_category(Notification $notification, string $key): void
    {
        $this->assertInstanceOf(HasMailCategory::class, $notification);
        $this->assertSame($key, $notification->mailCategory());

        $registry = $this->app->make(MailCategoryRegistryInterface::class);
        $this->assertNotNull($registry->find($key));
        $this->assertSame($key, $registry->forNotification($notification)?->key);
    }

    public function test_the_module_registers_one_mandatory_and_two_optional_categories(): void
    {
        $registry = $this->app->make(MailCategoryRegistryInterface::class);

        $this->assertTrue($registry->find(VenueBookingsServiceProvider::MAIL_MY_BOOKINGS)?->isMandatory());
        $this->assertFalse($registry->find(VenueBookingsServiceProvider::MAIL_REMINDERS)?->isMandatory());
        $this->assertFalse($registry->find(VenueBookingsServiceProvider::MAIL_MANAGEMENT)?->isMandatory());
    }

    public function test_a_reminder_the_person_turned_off_is_held_back(): void
    {
        $user = UserModel::factory()->create();
        $this->app->make(MailPreferenceServiceInterface::class)
            ->update($user, [VenueBookingsServiceProvider::MAIL_REMINDERS => false]);

        $reminder = new BookingReminderNotification('b1', 'John', 'Sala', '2026-03-02', '10:00', '12:00');

        $this->assertFalse($this->isSent($user, $reminder));
    }

    public function test_a_reminder_goes_out_by_default(): void
    {
        $user = UserModel::factory()->create();
        $reminder = new BookingReminderNotification('b1', 'John', 'Sala', '2026-03-02', '10:00', '12:00');

        $this->assertTrue($this->isSent($user, $reminder));
    }

    public function test_a_new_booking_notice_the_manager_turned_off_is_held_back(): void
    {
        $manager = $this->editorWithPermissions(['venuebookings:bookings.manage']);
        $this->app->make(MailPreferenceServiceInterface::class)
            ->update($manager, [VenueBookingsServiceProvider::MAIL_MANAGEMENT => false]);

        $created = new BookingCreatedNotification('b1', 'John', 'Sala', '2026-03-02', '10:00', '12:00');

        $this->assertFalse($this->isSent($manager, $created));
    }

    public function test_booking_confirmations_cannot_be_turned_off(): void
    {
        $user = UserModel::factory()->create();
        $confirmed = new BookingConfirmedNotification('b1', 'John', 'Sala', '2026-03-02', '10:00', '12:00');

        $this->assertTrue($this->isSent($user, $confirmed));
    }

    public function test_only_people_who_manage_bookings_see_the_management_category(): void
    {
        $preferences = $this->app->make(MailPreferenceServiceInterface::class);
        $keys = static fn (object $user): array => collect($preferences->sectionsFor($user))
            ->flatMap(static fn ($section) => $section->categories)
            ->map(static fn ($row) => $row->key)
            ->all();

        $member = UserModel::factory()->create();
        $manager = $this->editorWithPermissions(['venuebookings:bookings.manage']);

        $this->assertNotContains(VenueBookingsServiceProvider::MAIL_MANAGEMENT, $keys($member));
        $this->assertContains(VenueBookingsServiceProvider::MAIL_MANAGEMENT, $keys($manager));
        $this->assertContains(VenueBookingsServiceProvider::MAIL_REMINDERS, $keys($member));
    }

    private function isSent(UserModel $user, Notification $notification): bool
    {
        return Event::until(new NotificationSending($user, $notification, 'mail')) !== false;
    }
}
