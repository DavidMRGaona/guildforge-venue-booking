<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\VenueBookings\Notifications\Concerns\FormatsBookingDate;

/**
 * Tells whoever made a booking that the staff moved it. Dates and times are the venue's
 * wall-clock values, as stored, so they are shown as they are.
 */
final class BookingMovedNotification extends Notification implements ShouldQueue
{
    use FormatsBookingDate;
    use Queueable;

    public function __construct(
        private readonly string $bookingId,
        private readonly string $userName,
        private readonly string $resourceName,
        private readonly string $date,
        private readonly string $startTime,
        private readonly string $endTime,
        private readonly string $previousResourceName,
        private readonly string $previousDate,
        private readonly string $previousStartTime,
        private readonly string $previousEndTime,
    ) {
        // Queued only once the move is committed
        $this->afterCommit();
    }

    /**
     * @return array<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $key = 'venue-bookings::messages.emails.booking_moved';

        return (new MailMessage)
            ->subject(__("{$key}.subject", [
                'resource' => $this->resourceName,
                'date' => $this->displayDate($this->date),
            ]))
            ->greeting(__("{$key}.greeting", ['name' => $this->userName]))
            ->line(__("{$key}.intro"))
            ->line('**'.__("{$key}.resource").':** '.$this->resourceName)
            ->line('**'.__("{$key}.date").':** '.$this->displayDate($this->date))
            ->line('**'.__("{$key}.time").':** '.$this->range($this->startTime, $this->endTime))
            ->line('**'.__("{$key}.before").':** '.$this->previousResourceName.', '
                .$this->displayDate($this->previousDate).', '
                .$this->range($this->previousStartTime, $this->previousEndTime))
            ->action(__("{$key}.view_bookings"), url('/reservas/mis-reservas'));
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
            'previous_resource_name' => $this->previousResourceName,
            'previous_date' => $this->previousDate,
            'previous_start_time' => $this->previousStartTime,
            'previous_end_time' => $this->previousEndTime,
            'type' => 'booking_moved',
        ];
    }

    private function range(string $start, string $end): string
    {
        return substr($start, 0, 5).' - '.substr($end, 0, 5);
    }
}
