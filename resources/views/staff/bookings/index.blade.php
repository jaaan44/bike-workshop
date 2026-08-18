<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Bookings" class="mb-0" />
    </x-slot>

    <x-empty-state
        title="No bookings yet"
        description="Incoming customer bookings will appear here once the booking workflow is in place."
    />
</x-app-layout>
