@php
    $partsByCategory = $booking->bicycleParts->groupBy(fn ($part) => $part->bicyclePartCategory->name);
    $shareableStatuses = [
        \App\Enums\BookingStatus::AwaitingCustomerApproval,
        \App\Enums\BookingStatus::RepairInProgress,
        \App\Enums\BookingStatus::RepairCompleted,
        \App\Enums\BookingStatus::QualityCheck,
        \App\Enums\BookingStatus::ReadyForPickup,
        \App\Enums\BookingStatus::ReadyForDelivery,
        \App\Enums\BookingStatus::Completed,
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$booking->reference_number" class="mb-0" />
    </x-slot>

    @if (session('status') === 'booking-created')
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
            Booking submitted &mdash; we'll review it and confirm your appointment.
        </div>
    @elseif (session('status') === 'repair-approved')
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
            Repairs approved &mdash; the workshop will get started.
        </div>
    @elseif (session('status') === 'repair-declined')
        <div class="mb-4 rounded-lg bg-gray-50 border border-gray-200 px-4 py-3 text-sm text-gray-700">
            Repairs declined. The workshop will follow up with you.
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 p-4 mb-4">
        <div class="flex items-start justify-between gap-2 mb-3">
            <div>
                <p class="font-semibold text-gray-900">{{ $booking->bicycle->nickname }}</p>
                <p class="text-sm text-gray-500">Appointment: {{ $booking->appointment_date->format('l, M j, Y') }}</p>
            </div>
            <x-status-badge :status="$booking->status" />
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-4 mb-4">
        <p class="text-xs font-semibold text-gray-500 uppercase mb-2">What Needs Attention</p>
        <div class="space-y-3">
            @foreach ($partsByCategory as $categoryName => $parts)
                <div>
                    <p class="text-sm font-medium text-gray-700">{{ $categoryName }}</p>
                    <div class="flex flex-wrap gap-1.5 mt-1">
                        @foreach ($parts as $part)
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs text-gray-700">
                                {{ $part->name }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @if ($booking->remarks)
        <div class="bg-white rounded-xl border border-gray-200 p-4 mb-4">
            <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Your Remarks</p>
            <p class="text-sm text-gray-900 whitespace-pre-line">{{ $booking->remarks }}</p>
        </div>
    @endif

    @if (in_array($booking->status, $shareableStatuses, true) && ($booking->inspection || $booking->repairItems->isNotEmpty()))
        <div class="bg-white rounded-xl border border-gray-200 p-4" x-data="">
            <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Proposed Repairs</p>

            @if ($booking->inspection?->recommended_repairs)
                <p class="text-sm text-gray-900 whitespace-pre-line mb-3">{{ $booking->inspection->recommended_repairs }}</p>
            @endif

            @if ($booking->repairItems->isNotEmpty())
                <ul class="space-y-1 mb-3">
                    @foreach ($booking->repairItems as $item)
                        <li class="text-sm text-gray-700">&bull; {{ $item->description }}</li>
                    @endforeach
                </ul>
            @endif

            @if ($booking->status === \App\Enums\BookingStatus::AwaitingCustomerApproval)
                <div class="grid grid-cols-2 gap-2 mt-3">
                    <form method="POST" action="{{ route('customer.repairs.approve', $booking) }}">
                        @csrf
                        <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                            Approve
                        </button>
                    </form>
                    <button type="button" x-on:click="$dispatch('open-modal', 'decline-repairs')"
                        class="w-full rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-red-600 border border-gray-300 hover:bg-gray-50">
                        Decline
                    </button>
                </div>

                <x-modal name="decline-repairs" focusable>
                    <form method="POST" action="{{ route('customer.repairs.decline', $booking) }}" class="p-6">
                        @csrf
                        <h2 class="text-lg font-medium text-gray-900">Decline these repairs?</h2>
                        <p class="mt-1 text-sm text-gray-600">
                            The workshop will be notified and will follow up with you before proceeding.
                        </p>
                        <div class="mt-6 flex justify-end gap-3">
                            <x-secondary-button type="button" x-on:click="$dispatch('close')">
                                Never Mind
                            </x-secondary-button>
                            <x-danger-button>Decline</x-danger-button>
                        </div>
                    </form>
                </x-modal>
            @endif
        </div>
    @endif
</x-app-layout>
