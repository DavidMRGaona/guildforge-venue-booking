<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Notifications;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Mail\Contracts\HasMailCategory;
use App\Application\Mail\Contracts\HasMailSubject;
use App\Application\SiteNotifications\Contracts\HasSiteNotification;
use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;
use App\Application\SiteNotifications\DTOs\SiteNotificationGroupDTO;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\VenueBookings\Domain\Entities\Booking;
use Modules\VenueBookings\Filament\Resources\BookingResource;
use Modules\VenueBookings\Notifications\Concerns\AnnouncesOnSite;
use Modules\VenueBookings\Notifications\Concerns\FormatsBookingDate;
use Modules\VenueBookings\VenueBookingsServiceProvider;

final class BookingCreatedNotification extends Notification implements HasMailCategory, HasMailSubject, HasSiteNotification, ShouldQueue
{
    use AnnouncesOnSite;
    use FormatsBookingDate;
    use Queueable;

    /** Unread new bookings for management are one notice. */
    public const string GROUP_KEY = 'venue-bookings.pending';

    private const string MANAGE_PERMISSION = 'venuebookings:bookings.manage';

    public function __construct(
        private readonly string $bookingId,
        private readonly string $userName,
        private readonly string $resourceName,
        private readonly string $date,
        private readonly string $startTime,
        private readonly string $endTime,
        private readonly ?string $actorId = null,
    ) {}

    public function mailCategory(): string
    {
        return VenueBookingsServiceProvider::MAIL_MANAGEMENT;
    }

    /**
     * @return array<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', HasSiteNotification::CHANNEL];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('venue-bookings::messages.emails.booking_created.subject', [
                'resource' => $this->resourceName,
                'date' => $this->displayDate($this->date),
            ]))
            ->greeting(__('venue-bookings::messages.emails.booking_created.greeting'))
            ->line(__('venue-bookings::messages.emails.booking_created.intro', [
                'user' => $this->userName,
            ]))
            ->line('**'.__('venue-bookings::messages.emails.booking_created.resource').':** '.$this->resourceName)
            ->line('**'.__('venue-bookings::messages.emails.booking_created.date').':** '.$this->displayDate($this->date))
            ->line('**'.__('venue-bookings::messages.emails.booking_created.time').':** '.$this->startTime.' - '.$this->endTime)
            ->action(__('venue-bookings::messages.emails.booking_created.view_booking'), BookingResource::getUrl('edit', ['record' => $this->bookingId]));
    }

    protected function siteTitle(): string
    {
        return __('venue-bookings::messages.notices.created', [...$this->siteReplacements(), 'user' => $this->userName]);
    }

    /**
     * The booking in the panel, as a path of this site.
     */
    protected function sitePath(): string
    {
        return BookingResource::getUrl('edit', ['record' => $this->bookingId], isAbsolute: false);
    }

    /**
     * Still for whoever manages bookings, asked of authorization when it is delivered (a role
     * may have been taken away or paused while the notice waited), and while the booking exists.
     */
    protected function stillVisibleTo(object $notifiable): bool
    {
        return app(AuthorizationServiceInterface::class)->can($notifiable, self::MANAGE_PERMISSION)
            && $this->booking() instanceof Booking;
    }

    protected function siteNotification(): SiteNotificationDTO
    {
        return new SiteNotificationDTO(
            title: $this->siteTitle(),
            url: $this->sitePath(),
            body: $this->startTime.' - '.$this->endTime,
            icon: $this->siteIcon(),
            groupKey: self::GROUP_KEY,
            actorId: $this->actorId,
            groupedTitle: static fn (SiteNotificationGroupDTO $group): string => __(
                'venue-bookings::messages.notices.created_grouped',
                ['count' => $group->count],
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->bookingId,
            'user_name' => $this->userName,
            'resource_name' => $this->resourceName,
            'date' => $this->date,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'type' => 'booking_created',
        ];
    }
}
