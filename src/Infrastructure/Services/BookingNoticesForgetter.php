<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Services;

use App\Application\Mail\Services\MailSubjectMuteServiceInterface;
use App\Application\SiteNotifications\Services\SiteNotificationServiceInterface;
use Modules\VenueBookings\Application\Services\BookingMailSubjects;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;

/**
 * Takes the bell notices and the mutes about a booking away once the booking is deleted, so
 * nobody is left with a link to something that is gone.
 */
final readonly class BookingNoticesForgetter
{
    public function __construct(
        private SiteNotificationServiceInterface $notices,
        private MailSubjectMuteServiceInterface $mutes,
    ) {}

    public function forgetBooking(string $bookingId): void
    {
        $this->mutes->forgetSubject(BookingMailSubjects::BOOKING, $bookingId);
        $this->notices->forgetSubject(BookingMailSubjects::BOOKING, $bookingId);
    }

    /**
     * Deleting a space deletes its bookings with it.
     */
    public function forgetBookingsOfResource(string $resourceId): void
    {
        BookingModel::query()
            ->where('resource_id', $resourceId)
            ->pluck('id')
            ->each(fn (mixed $id) => $this->forgetBooking((string) $id));
    }
}
