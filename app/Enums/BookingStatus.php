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
}
