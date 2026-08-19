<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Jobs" class="mb-0" />
    </x-slot>

    @if ($jobs->isEmpty())
        <x-empty-state
            title="No repair jobs yet"
            description="Once a bicycle is received, it shows up here for inspection and repair work."
        />
    @else
        <div class="space-y-3">
            @foreach ($jobs as $booking)
                <a href="{{ route('staff.bookings.show', $booking) }}" class="block bg-white rounded-xl border border-gray-200 p-4 hover:border-gray-300">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-semibold text-gray-900">{{ $booking->bicycle->nickname }}</p>
                            <p class="text-xs text-gray-500">{{ $booking->user->name }} &middot; {{ $booking->reference_number }}</p>
                            <p class="text-xs text-gray-500">
                                {{ $booking->assignedTechnician?->name ?? 'Unassigned' }}
                            </p>
                        </div>
                        <x-status-badge :status="$booking->status" />
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-app-layout>
