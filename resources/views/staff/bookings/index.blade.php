<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Bookings" class="mb-0" />
    </x-slot>

    @if ($bookings->isEmpty())
        <x-empty-state
            title="No bookings yet"
            description="Incoming customer bookings will appear here once customers start booking repairs."
        />
    @else
        <div class="space-y-3">
            @foreach ($bookings as $booking)
                <a href="{{ route('staff.bookings.show', $booking) }}" class="block bg-white rounded-xl border border-gray-200 p-4 hover:border-gray-300">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-semibold text-gray-900">{{ $booking->bicycle->nickname }}</p>
                            <p class="text-xs text-gray-500">{{ $booking->user->name }} &middot; {{ $booking->reference_number }}</p>
                            <p class="text-xs text-gray-500">{{ $booking->appointment_date->format('M j, Y') }}</p>
                        </div>
                        <x-status-badge :status="$booking->status" />
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-app-layout>
