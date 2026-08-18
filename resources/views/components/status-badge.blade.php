@props(['status'])

@php
    $colors = match ($status) {
        \App\Enums\BookingStatus::Pending => 'bg-gray-100 text-gray-700',
        \App\Enums\BookingStatus::Accepted, \App\Enums\BookingStatus::Scheduled => 'bg-blue-100 text-blue-700',
        \App\Enums\BookingStatus::BikeReceived, \App\Enums\BookingStatus::Inspection => 'bg-amber-100 text-amber-700',
        \App\Enums\BookingStatus::AwaitingCustomerApproval => 'bg-orange-100 text-orange-700',
        \App\Enums\BookingStatus::RepairInProgress => 'bg-indigo-100 text-indigo-700',
        \App\Enums\BookingStatus::RepairCompleted, \App\Enums\BookingStatus::QualityCheck => 'bg-purple-100 text-purple-700',
        \App\Enums\BookingStatus::ReadyForPickup, \App\Enums\BookingStatus::ReadyForDelivery => 'bg-teal-100 text-teal-700',
        \App\Enums\BookingStatus::Completed => 'bg-green-100 text-green-700',
        \App\Enums\BookingStatus::Cancelled => 'bg-red-100 text-red-700',
        default => 'bg-gray-100 text-gray-700',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium $colors"]) }}>
    {{ $status->label() }}
</span>
