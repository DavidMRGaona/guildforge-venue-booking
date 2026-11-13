<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Application\Services;

use App\Application\Mail\DTOs\MailSubjectDTO;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * What the module's notices are about, for the core's mutes and for forgetting them: one booking.
 * The same type and id tie every mail and bell notice of a booking together, so deleting the
 * booking can take them all away at once.
 */
final class BookingMailSubjects
{
    public const string BOOKING = 'venue-bookings.booking';

    public static function booking(string $bookingId, string $resourceName, string $date): MailSubjectDTO
    {
        return new MailSubjectDTO(
            self::BOOKING,
            $bookingId,
            $resourceName.', '.self::displayDate($date),
            'venue-bookings::messages.notices.subject_booking',
        );
    }

    /**
     * The booking day as people read it (16/03/2026); the stored value if it cannot be parsed.
     */
    public static function displayDate(string $date): string
    {
        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $date)?->format('d/m/Y') ?? $date;
        } catch (Throwable) {
            return $date;
        }
    }
}
