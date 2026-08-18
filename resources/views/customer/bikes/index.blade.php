<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Your Bikes" class="mb-0" />
    </x-slot>

    <x-empty-state
        title="You haven't added a bicycle yet."
        description="Add your bicycle so you can book repairs and keep its service history in one place."
        action-label="Add Bicycle"
        action-href="#"
    />

    <p class="mt-4 text-center text-xs text-gray-400">Bicycle registration is coming soon.</p>
</x-app-layout>
