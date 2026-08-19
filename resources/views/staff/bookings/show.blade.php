@php
    $partsByCategory = $booking->bicycleParts->groupBy(fn ($part) => $part->bicyclePartCategory->name);
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$booking->reference_number" class="mb-0" />
    </x-slot>

    @if (session('status') === 'booking-accepted')
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">Booking accepted.</div>
    @elseif (session('status') === 'bike-received')
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">Bike marked as received.</div>
    @elseif (session('status') === 'booking-cancelled')
        <div class="mb-4 rounded-lg bg-gray-50 border border-gray-200 px-4 py-3 text-sm text-gray-700">Booking cancelled.</div>
    @endif

    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 p-4 mb-4">
        <div class="flex items-start justify-between gap-2 mb-3">
            <div>
                <p class="font-semibold text-gray-900">{{ $booking->bicycle->nickname }}</p>
                <p class="text-sm text-gray-500">{{ $booking->bicycle->bicycleType->name }}</p>
            </div>
            <x-status-badge :status="$booking->status" />
        </div>
        <div class="text-sm text-gray-700 space-y-0.5">
            <p><span class="text-gray-500">Customer:</span> {{ $booking->user->name }}</p>
            @if ($booking->user->phone)
                <p><span class="text-gray-500">Phone:</span> {{ $booking->user->phone }}</p>
            @endif
            <p><span class="text-gray-500">Appointment:</span> {{ $booking->appointment_date->format('l, M j, Y') }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-4 mb-4">
        <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Customer Reported Issues</p>
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

        @if ($booking->remarks)
            <div class="mt-4 pt-4 border-t border-gray-100">
                <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Customer Remarks</p>
                <p class="text-sm text-gray-900 whitespace-pre-line">&ldquo;{{ $booking->remarks }}&rdquo;</p>
            </div>
        @endif
    </div>

    <!-- Actions -->
    <div class="space-y-3 mb-4" x-data="">
        @if ($booking->status === \App\Enums\BookingStatus::Pending)
            <form method="POST" action="{{ route('staff.bookings.accept', $booking) }}">
                @csrf
                <button type="submit" class="w-full rounded-lg bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                    Accept Booking
                </button>
            </form>
        @elseif ($booking->status === \App\Enums\BookingStatus::Accepted)
            <form method="POST" action="{{ route('staff.bookings.receive', $booking) }}">
                @csrf
                <button type="submit" class="w-full rounded-lg bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                    Receive Bicycle
                </button>
            </form>
        @endif

        @if (in_array($booking->status, [\App\Enums\BookingStatus::Pending, \App\Enums\BookingStatus::Accepted], true))
            <button type="button" x-on:click="$dispatch('open-modal', 'cancel-booking')"
                class="w-full rounded-lg bg-white px-5 py-3 text-sm font-semibold text-red-600 border border-gray-300 hover:bg-gray-50">
                Cancel Booking
            </button>

            <x-modal name="cancel-booking" focusable>
                <form method="POST" action="{{ route('staff.bookings.cancel', $booking) }}" class="p-6">
                    @csrf
                    <h2 class="text-lg font-medium text-gray-900">Cancel this booking?</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        The customer will see this booking as cancelled. This can't be undone.
                    </p>
                    <div class="mt-6 flex justify-end gap-3">
                        <x-secondary-button type="button" x-on:click="$dispatch('close')">
                            Never Mind
                        </x-secondary-button>
                        <x-danger-button>Cancel Booking</x-danger-button>
                    </div>
                </form>
            </x-modal>
        @endif
    </div>

    <!-- Status history -->
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <p class="text-xs font-semibold text-gray-500 uppercase mb-3">History</p>
        <ul class="space-y-2">
            <li class="text-sm text-gray-500">
                <span class="text-gray-900 font-medium">Booking Received</span>
                &middot; {{ $booking->created_at->format('M j, Y g:ia') }}
            </li>
            @foreach ($booking->statusHistories->sortBy('id') as $history)
                <li class="text-sm text-gray-500">
                    <span class="text-gray-900 font-medium">{{ $history->new_status->label() }}</span>
                    @if ($history->changedBy)
                        by {{ $history->changedBy->name }}
                    @endif
                    &middot; {{ $history->created_at->format('M j, Y g:ia') }}
                </li>
            @endforeach
        </ul>
    </div>
</x-app-layout>
