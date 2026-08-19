<x-app-layout>
    <p class="text-sm text-gray-500">{{ now()->format('A') === 'AM' ? 'Good morning' : 'Good afternoon' }},</p>
    <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ $user->name }}</h1>

    <section class="mb-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-2">Needs Attention</h2>
        <div class="grid grid-cols-3 gap-3">
            <a href="{{ route('staff.bookings.index') }}" class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                <p class="text-2xl font-bold text-gray-900">{{ $newBookingsCount }}</p>
                <p class="text-xs text-gray-500 mt-1">New Bookings</p>
            </a>
            <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                <p class="text-2xl font-bold text-gray-900">0</p>
                <p class="text-xs text-gray-500 mt-1">Awaiting Approval</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                <p class="text-2xl font-bold text-gray-900">0</p>
                <p class="text-xs text-gray-500 mt-1">Quality Check</p>
            </div>
        </div>
    </section>

    <section class="mb-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-2">Workshop Today</h2>
        <div class="grid grid-cols-4 gap-3">
            <div class="bg-white rounded-xl border border-gray-200 p-3 text-center">
                <p class="text-lg font-bold text-gray-900">{{ $acceptedCount }}</p>
                <p class="text-[11px] text-gray-500 mt-1">Accepted</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-3 text-center">
                <p class="text-lg font-bold text-gray-900">{{ $bikeReceivedCount }}</p>
                <p class="text-[11px] text-gray-500 mt-1">Received</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-3 text-center">
                <p class="text-lg font-bold text-gray-900">0</p>
                <p class="text-[11px] text-gray-500 mt-1">In Repair</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-3 text-center">
                <p class="text-lg font-bold text-gray-900">0</p>
                <p class="text-[11px] text-gray-500 mt-1">Ready</p>
            </div>
        </div>
    </section>

    <section>
        <h2 class="text-sm font-semibold text-gray-700 mb-2">Active Jobs</h2>

        @if ($activeBookings->isEmpty())
            <x-empty-state
                title="No active jobs yet"
                description="Accepted bookings and received bikes will show up here."
            />
        @else
            <div class="space-y-3">
                @foreach ($activeBookings as $booking)
                    <a href="{{ route('staff.bookings.show', $booking) }}" class="block bg-white rounded-xl border border-gray-200 p-4 hover:border-gray-300">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-semibold text-gray-900">{{ $booking->bicycle->nickname }}</p>
                                <p class="text-xs text-gray-500">{{ $booking->user->name }} &middot; {{ $booking->reference_number }}</p>
                            </div>
                            <x-status-badge :status="$booking->status" />
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
</x-app-layout>
