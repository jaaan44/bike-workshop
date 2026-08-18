<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Your Bikes" class="mb-0" />
    </x-slot>

    @if ($bicycles->isEmpty())
        <x-empty-state
            title="You haven't added a bicycle yet."
            description="Add your bicycle so you can book repairs and keep its service history in one place."
            action-label="Add Bicycle"
            :action-href="route('customer.bikes.create')"
        />
    @else
        <a href="{{ route('customer.bikes.create') }}" class="block text-center mb-4 rounded-lg bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
            + Add Bicycle
        </a>

        <div class="space-y-3">
            @foreach ($bicycles as $bicycle)
                <a href="{{ route('customer.bikes.show', $bicycle) }}" class="block bg-white rounded-xl border border-gray-200 p-4 hover:border-gray-300">
                    <p class="font-semibold text-gray-900">{{ $bicycle->nickname }}</p>
                    <p class="text-sm text-gray-500">
                        {{ $bicycle->bicycleType->name }}
                        @if ($bicycle->brand || $bicycle->model)
                            &middot; {{ trim("{$bicycle->brand} {$bicycle->model}") }}
                        @endif
                    </p>
                </a>
            @endforeach
        </div>
    @endif
</x-app-layout>
