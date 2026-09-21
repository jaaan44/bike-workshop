<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Scheduled = 'scheduled';
    case BikeReceived = 'bike_received';
    case Inspection = 'inspection';
    case AwaitingCustomerApproval = 'awaiting_customer_approval';
    case RepairInProgress = 'repair_in_progress';
    case RepairCompleted = 'repair_completed';
    case QualityCheck = 'quality_check';
    case ReadyForPickup = 'ready_for_pickup';
    case ReadyForDelivery = 'ready_for_delivery';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * A terminal status is a closed job: nothing about the repair itself
     * (inspection, repair items, notes, technician assignment) should be
     * editable once a booking reaches one of these. Only two of the 13
     * cases are actually terminal — every other status is still "in flight".
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Cancelled => true,
            default => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Accepted => 'Accepted',
            self::Scheduled => 'Scheduled',
            self::BikeReceived => 'Bike Received',
            self::Inspection => 'Inspection',
            self::AwaitingCustomerApproval => 'Awaiting Your Approval',
            self::RepairInProgress => 'Repair In Progress',
            self::RepairCompleted => 'Repair Completed',
            self::QualityCheck => 'Quality Check',
            self::ReadyForPickup => 'Ready for Pickup',
            self::ReadyForDelivery => 'Ready for Delivery',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * The customer-facing message to notify the booking's owner with when a
     * transition lands on this status, or null if this status doesn't
     * warrant a notification (purely internal/administrative stages).
     *
     * This is the single source of truth for which lifecycle transitions
     * notify the customer — see Booking::transitionTo().
     */
    public function customerNotificationMessage(): ?string
    {
        return match ($this) {
            self::Accepted => 'Your repair booking has been accepted.',
            self::BikeReceived => "We've received your bicycle.",
            self::AwaitingCustomerApproval => 'Your bicycle inspection is complete. Please review and approve the proposed repair work.',
            self::RepairInProgress => 'Repair work on your bicycle has started.',
            self::RepairCompleted => 'Repair work on your bicycle has been completed and is awaiting quality inspection.',
            self::ReadyForPickup => 'Your bicycle is ready for pickup.',
            self::ReadyForDelivery => 'Your bicycle is ready for delivery.',
            self::Completed => 'Your bicycle repair has been completed.',
            default => null,
        };
    }
}
