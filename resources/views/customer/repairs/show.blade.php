@php
    $partsByCategory = $booking->bicycleParts->groupBy(fn ($part) => $part->bicyclePartCategory->name);
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$booking->reference_number" class="mb-0" />
    </x-slot>

    @if (session('status') === 'booking-created')
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
            Booking submitted &mdash; we'll review it and confirm your appointment.
        </div>
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
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Your Remarks</p>
            <p class="text-sm text-gray-900 whitespace-pre-line">{{ $booking->remarks }}</p>
        </div>
    @endif
</x-app-layout>
