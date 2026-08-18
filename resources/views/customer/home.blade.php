<x-app-layout>
    <p class="text-sm text-gray-500">{{ now()->format('A') === 'AM' ? 'Good morning' : 'Good afternoon' }},</p>
    <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ $user->name }}</h1>

    <section class="mb-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-2">Your Bikes</h2>

        <x-empty-state
            title="You haven't added a bicycle yet."
            description="Add your bicycle so you can book repairs and keep its service history in one place."
            action-label="Add Bicycle"
            :action-href="route('customer.bikes.index')"
        />
    </section>

    <section>
        <h2 class="text-sm font-semibold text-gray-700 mb-2">Recent Repairs</h2>

        <x-empty-state
            title="No repairs yet"
            description="When your bicycle needs attention, you can create a repair booking here."
            action-label="Book a Repair"
            :action-href="route('customer.repairs.index')"
        />
    </section>
</x-app-layout>
