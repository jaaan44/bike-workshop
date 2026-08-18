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

    <a href="{{ route('customer.bikes.edit', $bicycle) }}" class="mt-4 block text-center rounded-lg bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
        Edit Bicycle
    </a>
</x-app-layout>
