<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Your Repairs" class="mb-0" />
    </x-slot>

    @if ($bookings->isEmpty())
        <x-empty-state
            title="No repairs yet"
            description="When your bicycle needs attention, you can create a repair booking here."
            action-label="Book a Repair"
            :action-href="route('customer.repairs.create')"
        />
    @else
        <a href="{{ route('customer.repairs.create') }}" class="block text-center mb-4 rounded-lg bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
            + Book a Repair
        </a>

        <div class="space-y-3">
            @foreach ($bookings as $booking)
                <a href="{{ route('customer.repairs.show', $booking) }}" class="block bg-white rounded-xl border border-gray-200 p-4 hover:border-gray-300">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-semibold text-gray-900">{{ $booking->bicycle->nickname }}</p>
                            <p class="text-xs text-gray-500">{{ $booking->reference_number }} &middot; {{ $booking->appointment_date->format('M j, Y') }}</p>
                        </div>
                        <x-status-badge :status="$booking->status" />
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-app-layout>
