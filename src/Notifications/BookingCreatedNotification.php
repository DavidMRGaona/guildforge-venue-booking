<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\VenueBookings\Filament\Resources\BookingResource;

final class BookingCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $bookingId,
        private readonly string $userName,
        private readonly string $resourceName,
        private readonly string $date,
        private readonly string $startTime,
        private readonly string $endTime,
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
        return (new MailMessage)
            ->subject(__('venue-bookings::messages.emails.booking_created.subject', [
                'resource' => $this->resourceName,
                'date' => $this->date,
            ]))
            ->greeting(__('venue-bookings::messages.emails.booking_created.greeting'))
            ->line(__('venue-bookings::messages.emails.booking_created.intro', [
                'user' => $this->userName,
            ]))
            ->line('**'.__('venue-bookings::messages.emails.booking_created.resource').':** '.$this->resourceName)
            ->line('**'.__('venue-bookings::messages.emails.booking_created.date').':** '.$this->date)
            ->line('**'.__('venue-bookings::messages.emails.booking_created.time').':** '.$this->startTime.' - '.$this->endTime)
            ->action(__('venue-bookings::messages.emails.booking_created.view_booking'), BookingResource::getUrl('edit', ['record' => $this->bookingId]));
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
