<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Your Repairs" class="mb-0" />
    </x-slot>

    <x-empty-state
        title="No repairs yet"
        description="When your bicycle needs attention, you can create a repair booking here."
        action-label="Book a Repair"
        action-href="#"
    />

    <p class="mt-4 text-center text-xs text-gray-400">Repair booking is coming soon.</p>
</x-app-layout>
