<x-app-layout>
    <p class="text-sm text-gray-500">{{ now()->format('A') === 'AM' ? 'Good morning' : 'Good afternoon' }},</p>
    <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ $user->name }}</h1>

    <section class="mb-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-2">Needs Attention</h2>
        <div class="grid grid-cols-3 gap-3">
            <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                <p class="text-2xl font-bold text-gray-900">0</p>
                <p class="text-xs text-gray-500 mt-1">New Bookings</p>
            </div>
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
                <p class="text-lg font-bold text-gray-900">0</p>
                <p class="text-[11px] text-gray-500 mt-1">Scheduled</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-3 text-center">
                <p class="text-lg font-bold text-gray-900">0</p>
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

        <x-empty-state
            title="No active jobs yet"
            description="Accepted bookings and repair jobs will show up here once the booking workflow is in place."
        />
    </section>
</x-app-layout>
