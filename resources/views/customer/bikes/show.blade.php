@php
    $fields = [
        'Type' => $bicycle->bicycleType->name,
        'Brand' => $bicycle->brand,
        'Model' => $bicycle->model,
        'Color' => $bicycle->color,
        'Wheel Size' => $bicycle->wheel_size,
        'Year' => $bicycle->year,
        'Serial Number' => $bicycle->serial_number,
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$bicycle->nickname" class="mb-0" />
    </x-slot>

    @if (session('status') === 'bicycle-created')
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
            Bicycle added.
        </div>
    @elseif (session('status') === 'bicycle-updated')
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
            Bicycle updated.
        </div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
        @foreach ($fields as $label => $value)
            @if ($value)
                <div class="flex justify-between px-4 py-3 text-sm">
                    <span class="text-gray-500">{{ $label }}</span>
                    <span class="text-gray-900 font-medium">{{ $value }}</span>
                </div>
            @endif
        @endforeach
    </div>

    @if ($bicycle->notes)
        <div class="mt-4 bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Notes</p>
            <p class="text-sm text-gray-900 whitespace-pre-line">{{ $bicycle->notes }}</p>
        </div>
    @endif

    <a href="{{ route('customer.bikes.edit', $bicycle) }}" class="mt-4 mb-6 block text-center rounded-lg bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
        Edit Bicycle
    </a>

    @if ($activeBookings->isNotEmpty())
        <div class="mb-6">
            <p class="text-xs font-semibold text-gray-500 uppercase mb-2">
                {{ $activeBookings->count() > 1 ? 'Current Repairs' : 'Current Repair' }}
            </p>
            <div class="space-y-2">
                @foreach ($activeBookings as $active)
                    <a href="{{ route('customer.repairs.show', $active) }}" class="block bg-white rounded-xl border border-gray-200 p-4 hover:border-indigo-300">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $active->reference_number }}</p>
                                <p class="text-xs text-gray-500">Appointment {{ $active->appointment_date->format('M j, Y') }}</p>
                            </div>
                            <x-status-badge :status="$active->status" />
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="mb-6">
        <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Service History</p>

        @if ($completedServiceCount === 0)
            <x-empty-state
                title="No completed service history yet."
                description="Completed repairs for this bicycle will show up here."
            />
        @else
            <div class="bg-white rounded-xl border border-gray-200 p-4 mb-3">
                <p class="text-sm text-gray-700">
                    {{ $completedServiceCount }} completed {{ $completedServiceCount === 1 ? 'service' : 'services' }}
                </p>
                @if ($lastServiceDate)
                    <p class="text-xs text-gray-500">Last serviced {{ $lastServiceDate->format('M j, Y') }}</p>
                @endif
            </div>

            <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Previous Service History</p>
            <div class="space-y-2">
                @foreach ($completedBookings as $past)
                    @php $fulfillment = $past->fulfillmentMethod(); @endphp
                    <a href="{{ route('customer.repairs.show', $past) }}" class="block bg-white rounded-xl border border-gray-200 p-4 hover:border-indigo-300">
                        <div class="flex items-start justify-between gap-2 mb-1">
                            <p class="text-sm font-medium text-gray-900">{{ $past->reference_number }}</p>
                            <p class="text-xs text-gray-400 whitespace-nowrap">{{ $past->updated_at->format('M j, Y') }}</p>
                        </div>
                        @if ($past->repairItems->isNotEmpty())
                            <p class="text-xs text-gray-600">
                                {{ $past->repairItems->pluck('description')->join(', ') }}
                            </p>
                        @endif
                        @if ($fulfillment)
                            <p class="text-xs text-gray-400 mt-1">
                                {{ $fulfillment === \App\Enums\BookingStatus::ReadyForPickup ? 'Picked up' : 'Delivered' }}
                            </p>
                        @endif
                    </a>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $completedBookings->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
