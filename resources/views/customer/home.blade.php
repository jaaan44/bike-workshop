<x-app-layout>
    <p class="text-sm text-gray-500">{{ now()->format('A') === 'AM' ? 'Good morning' : 'Good afternoon' }},</p>
    <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ $user->name }}</h1>

    <section class="mb-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-2">Your Bikes</h2>

        @if ($bicycles->isEmpty())
            <x-empty-state
                title="You haven't added a bicycle yet."
                description="Add your bicycle so you can book repairs and keep its service history in one place."
                action-label="Add Bicycle"
                :action-href="route('customer.bikes.create')"
            />
        @else
            <div class="space-y-3">
                @foreach ($bicycles as $bicycle)
                    <a href="{{ route('customer.bikes.show', $bicycle) }}" class="block bg-white rounded-xl border border-gray-200 p-4 hover:border-gray-300">
                        <p class="font-semibold text-gray-900">{{ $bicycle->nickname }}</p>
                        <p class="text-sm text-gray-500">{{ $bicycle->bicycleType->name }}</p>
                    </a>
                @endforeach

                <a href="{{ route('customer.bikes.create') }}" class="block text-center rounded-lg bg-white px-5 py-3 text-sm font-semibold text-gray-900 border border-gray-300 hover:bg-gray-50">
                    + Add Another Bicycle
                </a>
            </div>
        @endif
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
