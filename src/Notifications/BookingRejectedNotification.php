<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class BookingRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $bookingId,
        private readonly string $userName,
        private readonly string $resourceName,
        private readonly string $date,
        private readonly string $startTime,
        private readonly string $endTime,
        private readonly ?string $reason = null,
    ) {}

    /**
     * @return array<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('venue-bookings::messages.emails.booking_rejected.subject', [
                'resource' => $this->resourceName,
                'date' => $this->date,
            ]))
            ->greeting(__('venue-bookings::messages.emails.booking_rejected.greeting', [
                'name' => $this->userName,
            ]))
            ->line(__('venue-bookings::messages.emails.booking_rejected.intro'))
            ->line('**'.__('venue-bookings::messages.emails.booking_rejected.resource').':** '.$this->resourceName)
            ->line('**'.__('venue-bookings::messages.emails.booking_rejected.date').':** '.$this->date)
            ->line('**'.__('venue-bookings::messages.emails.booking_rejected.time').':** '.$this->startTime.' - '.$this->endTime);

        if ($this->reason !== null) {
            $mail->line('**'.__('venue-bookings::messages.emails.booking_rejected.reason').':** '.$this->reason);
        }

        return $mail->action(__('venue-bookings::messages.emails.booking_rejected.view_bookings'), url('/reservas/mis-reservas'));
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
            'reason' => $this->reason,
            'type' => 'booking_rejected',
        ];
    }
}
