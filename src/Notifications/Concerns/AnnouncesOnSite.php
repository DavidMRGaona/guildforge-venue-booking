<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Notifications\Concerns;

use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;
use Modules\VenueBookings\Application\Services\BookingMailSubjects;
use Modules\VenueBookings\Domain\Entities\Booking;
use Modules\VenueBookings\Domain\Repositories\BookingRepositoryInterface;
use Modules\VenueBookings\Domain\ValueObjects\BookingId;

/**
 * The bell notice of a booking notification. It reads what the notification already holds
 * (bookingId, resourceName, date, startTime, endTime), shows the booking's day as the mails do
 * and is checked when it is delivered, not when it was queued.
 */
trait AnnouncesOnSite
{
    public function mailSubject(): ?MailSubjectDTO
    {
        return BookingMailSubjects::booking($this->bookingId, $this->resourceName, $this->date);
    }

    public function toSiteNotification(object $notifiable): ?SiteNotificationDTO
    {
        if (! $this->stillVisibleTo($notifiable)) {
            return null;
        }

        return $this->siteNotification();
    }

    abstract protected function siteTitle(): string;

    /**
     * Where the notice leads: «Mis reservas», a path of this site.
     */
    protected function sitePath(): string
    {
        return route('bookings.my-bookings', absolute: false);
    }

    protected function siteIcon(): string
    {
        return 'calendar-days';
    }

    /**
     * By default, the person may see it while the booking still exists and is theirs.
     */
    protected function stillVisibleTo(object $notifiable): bool
    {
        $booking = $this->booking();

        return $booking instanceof Booking
            && method_exists($notifiable, 'getKey')
            && $booking->belongsTo((string) $notifiable->getKey());
    }

    protected function booking(): ?Booking
    {
        return app(BookingRepositoryInterface::class)->find(BookingId::fromString($this->bookingId));
    }

    protected function siteNotification(): SiteNotificationDTO
    {
        return new SiteNotificationDTO(
            title: $this->siteTitle(),
            url: $this->sitePath(),
            body: $this->startTime.' - '.$this->endTime,
            icon: $this->siteIcon(),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function siteReplacements(): array
    {
        return [
            'resource' => $this->resourceName,
            'date' => BookingMailSubjects::displayDate($this->date),
        ];
    }
}
