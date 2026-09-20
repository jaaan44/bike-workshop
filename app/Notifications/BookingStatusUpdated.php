<?php

namespace App\Notifications;

use App\Enums\BookingStatus;
use Illuminate\Notifications\Notification;

/**
 * Alerts the owning customer that their booking reached a lifecycle stage
 * that requires their attention or represents meaningful progress. Dispatched
 * exclusively from Booking::transitionTo() — see that method for which
 * statuses trigger it (BookingStatus::customerNotificationMessage()).
 */
class BookingStatusUpdated extends Notification
{
    public function __construct(
        private readonly int $bookingId,
        private readonly string $bookingReferenceNumber,
        private readonly BookingStatus $status,
        private readonly string $message,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->bookingId,
            'booking_reference_number' => $this->bookingReferenceNumber,
            'status' => $this->status->value,
            'message' => $this->message,
        ];
    }
}
