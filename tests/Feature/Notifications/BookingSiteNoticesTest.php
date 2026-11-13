<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Notifications;

use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\SiteNotifications\Contracts\HasSiteNotification;
use App\Application\SiteNotifications\DTOs\SiteNotificationItemDTO;
use App\Application\SiteNotifications\Services\SiteNotificationServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Closure;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use Modules\VenueBookings\Domain\Repositories\BookableResourceRepositoryInterface;
use Modules\VenueBookings\Domain\Repositories\BookingRepositoryInterface;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Modules\VenueBookings\Domain\ValueObjects\BookingId;
use Modules\VenueBookings\Filament\Resources\BookingResource;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
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
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The bell shows what happens to a person's booking, and the new bookings to whoever manages them.
 */
final class BookingSiteNoticesTest extends ModuleTestCase
{
    use CreatesUsersWithPermissions;
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    private BookableResourceModel $room;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
        $this->registerOnAdminPanel([BookingResource::class]);

        $this->room = BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sala grande',
            'slug' => 'sala-grande',
            'status' => 'active',
            'sort_order' => 0,
        ]);
    }

    /**
     * @return array<string, array{0: Closure(string): Notification}>
     */
    public static function memberNotifications(): array
    {
        $args = ['John', 'Sala grande', '2026-03-02', '10:00', '12:00'];

        return [
            'received' => [static fn (string $id): Notification => new BookingReceivedNotification($id, ...$args)],
            'confirmed' => [static fn (string $id): Notification => new BookingConfirmedNotification($id, ...$args)],
            'rejected' => [static fn (string $id): Notification => new BookingRejectedNotification($id, ...$args)],
            'cancelled' => [static fn (string $id): Notification => new BookingCancelledNotification($id, ...$args)],
            'reminder' => [static fn (string $id): Notification => new BookingReminderNotification($id, ...$args)],
        ];
    }

    public function test_the_three_categories_show_on_the_site(): void
    {
        $registry = $this->app->make(MailCategoryRegistryInterface::class);

        foreach ([VenueBookingsServiceProvider::MAIL_MY_BOOKINGS, VenueBookingsServiceProvider::MAIL_REMINDERS, VenueBookingsServiceProvider::MAIL_MANAGEMENT] as $key) {
            $this->assertTrue($registry->find($key)?->site, $key);
        }
    }

    /**
     * @param  Closure(string): Notification  $make
     */
    #[DataProvider('memberNotifications')]
    public function test_a_change_to_a_booking_reaches_its_member_with_a_link_to_my_bookings(Closure $make): void
    {
        $member = UserModel::factory()->create();
        $booking = $this->booking($member);

        $member->notifyNow($make($booking->id));

        $notice = $this->onlyNoticeOf($member);
        $this->assertSame('/reservas/mis-reservas', $notice->url);
        $this->assertStringContainsString('Sala grande', $notice->title);
        $this->assertStringContainsString('02/03/2026', $notice->title);
        $this->assertSame('10:00 - 12:00', $notice->body);
    }

    public function test_a_moved_booking_reaches_its_member(): void
    {
        $member = UserModel::factory()->create();
        $booking = $this->booking($member);

        $member->notifyNow(new BookingMovedNotification($booking->id, 'John', 'Sala grande', '2026-03-02', '10:00', '12:00', 'Sala pequeña', '2026-03-01', '10:00', '12:00'));

        $notice = $this->onlyNoticeOf($member);
        $this->assertSame('/reservas/mis-reservas', $notice->url);
        $this->assertStringContainsString('02/03/2026', $notice->title);
    }

    public function test_nothing_is_stored_once_the_booking_is_gone(): void
    {
        $member = UserModel::factory()->create();
        $booking = $this->booking($member);
        $booking->delete();

        $member->notifyNow(new BookingConfirmedNotification($booking->id, 'John', 'Sala grande', '2026-03-02', '10:00', '12:00'));

        $this->assertSame([], $this->noticesOf($member));
    }

    public function test_nothing_is_stored_for_someone_who_does_not_own_the_booking(): void
    {
        $owner = UserModel::factory()->create();
        $other = UserModel::factory()->create();
        $booking = $this->booking($owner);

        $other->notifyNow(new BookingConfirmedNotification($booking->id, 'John', 'Sala grande', '2026-03-02', '10:00', '12:00'));

        $this->assertSame([], $this->noticesOf($other));
    }

    public function test_every_notification_is_about_one_booking_so_it_can_be_forgotten_with_it(): void
    {
        $args = ['b1', 'John', 'Sala grande', '2026-03-02', '10:00', '12:00'];
        $all = [
            new BookingReceivedNotification(...$args),
            new BookingConfirmedNotification(...$args),
            new BookingRejectedNotification(...$args),
            new BookingCancelledNotification(...$args),
            new BookingReminderNotification(...$args),
            new BookingCreatedNotification(...$args),
            new BookingMovedNotification(...$args, ...['Sala', '2026-03-01', '10:00', '12:00']),
        ];

        foreach ($all as $notification) {
            $this->assertContains(HasSiteNotification::CHANNEL, $notification->via(new UserModel));
            $subject = $notification->mailSubject();
            $this->assertSame('venue-bookings.booking', $subject?->type);
            $this->assertSame('b1', $subject->id);
        }
    }

    public function test_new_bookings_for_management_gather_in_one_notice_while_unread(): void
    {
        $manager = $this->editorWithPermissions(['venuebookings:bookings.manage']);
        $first = $this->booking(UserModel::factory()->create());
        $second = $this->booking(UserModel::factory()->create());

        $manager->notifyNow(new BookingCreatedNotification($first->id, 'Ana', 'Sala grande', '2026-03-02', '10:00', '12:00', (string) $first->user_id));
        $manager->notifyNow(new BookingCreatedNotification($second->id, 'Luis', 'Sala grande', '2026-03-03', '10:00', '12:00', (string) $second->user_id));

        $notice = $this->onlyNoticeOf($manager);
        $this->assertSame(2, $notice->count);
        $this->assertSame(BookingResource::getUrl('edit', ['record' => $second->id], isAbsolute: false), $notice->url);
        $this->assertStringNotContainsString('http', $notice->url);
        $this->assertStringContainsString('2', $notice->title);
    }

    public function test_a_single_new_booking_names_who_booked_and_when(): void
    {
        $manager = $this->editorWithPermissions(['venuebookings:bookings.manage']);
        $booking = $this->booking(UserModel::factory()->create());

        $manager->notifyNow(new BookingCreatedNotification($booking->id, 'Ana', 'Sala grande', '2026-03-02', '10:00', '12:00', (string) $booking->user_id));

        $notice = $this->onlyNoticeOf($manager);
        $this->assertStringContainsString('Ana', $notice->title);
        $this->assertStringContainsString('Sala grande', $notice->title);
        $this->assertStringContainsString('02/03/2026', $notice->title);
    }

    public function test_someone_who_lost_the_manage_permission_gets_nothing(): void
    {
        $former = UserModel::factory()->create();
        $booking = $this->booking(UserModel::factory()->create());

        $former->notifyNow(new BookingCreatedNotification($booking->id, 'Ana', 'Sala grande', '2026-03-02', '10:00', '12:00', (string) $booking->user_id));

        $this->assertSame([], $this->noticesOf($former));
    }

    public function test_a_manager_who_made_the_booking_is_not_told_about_it(): void
    {
        $manager = $this->editorWithPermissions(['venuebookings:bookings.manage']);
        $booking = $this->booking($manager);

        $manager->notifyNow(new BookingCreatedNotification($booking->id, 'Yo', 'Sala grande', '2026-03-02', '10:00', '12:00', (string) $manager->id));

        $this->assertSame([], $this->noticesOf($manager));
    }

    public function test_deleting_a_booking_in_the_panel_forgets_its_notices(): void
    {
        $manager = $this->editorWithPermissions(['venuebookings:bookings.manage']);
        $booking = $this->booking(UserModel::factory()->create());
        $manager->notifyNow(new BookingCreatedNotification($booking->id, 'Ana', 'Sala grande', '2026-03-02', '10:00', '12:00', (string) $booking->user_id));
        $this->assertCount(1, $this->noticesOf($manager));

        $booking->delete();

        $this->assertSame([], $this->noticesOf($manager));
    }

    public function test_deleting_a_booking_through_the_repository_forgets_its_notices(): void
    {
        $member = UserModel::factory()->create();
        $booking = $this->booking($member);
        $member->notifyNow(new BookingConfirmedNotification($booking->id, 'John', 'Sala grande', '2026-03-02', '10:00', '12:00'));

        $this->app->make(BookingRepositoryInterface::class)->delete(BookingId::fromString($booking->id));

        $this->assertSame([], $this->noticesOf($member));
    }

    public function test_deleting_a_space_forgets_the_notices_of_its_bookings(): void
    {
        $member = UserModel::factory()->create();
        $booking = $this->booking($member);
        $member->notifyNow(new BookingConfirmedNotification($booking->id, 'John', 'Sala grande', '2026-03-02', '10:00', '12:00'));

        $this->room->delete();

        $this->assertSame([], $this->noticesOf($member));
    }

    public function test_deleting_a_space_through_its_repository_forgets_the_notices_of_its_bookings(): void
    {
        $member = UserModel::factory()->create();
        $booking = $this->booking($member);
        $member->notifyNow(new BookingConfirmedNotification($booking->id, 'John', 'Sala grande', '2026-03-02', '10:00', '12:00'));

        // A query-builder delete fires no model event and the database cascades the bookings
        $this->app->make(BookableResourceRepositoryInterface::class)->delete(BookableResourceId::fromString($this->room->id));

        $this->assertSame([], $this->noticesOf($member));
        $this->assertNull(BookingModel::find($booking->id));
    }

    public function test_the_notices_of_other_bookings_stay_when_one_is_deleted(): void
    {
        $member = UserModel::factory()->create();
        $gone = $this->booking($member);
        $kept = $this->booking($member);
        $member->notifyNow(new BookingConfirmedNotification($gone->id, 'John', 'Sala grande', '2026-03-02', '10:00', '12:00'));
        $member->notifyNow(new BookingRejectedNotification($kept->id, 'John', 'Sala grande', '2026-03-09', '10:00', '12:00'));

        $gone->delete();

        $this->assertCount(1, $this->noticesOf($member));
    }

    private function booking(UserModel $user): BookingModel
    {
        return BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->room->id,
            'user_id' => $user->id,
            'date' => '2026-03-02',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'pending',
        ]);
    }

    /**
     * @return list<SiteNotificationItemDTO>
     */
    private function noticesOf(UserModel $user): array
    {
        return $this->app->make(SiteNotificationServiceInterface::class)->latest($user, 20);
    }

    private function onlyNoticeOf(UserModel $user): SiteNotificationItemDTO
    {
        $notices = $this->noticesOf($user);
        $this->assertCount(1, $notices);

        return $notices[0];
    }
}
