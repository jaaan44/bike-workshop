<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Add Bicycle" class="mb-0" />
    </x-slot>

    <form method="POST" action="{{ route('customer.bikes.store') }}" class="bg-white rounded-xl border border-gray-200 p-5">
        @csrf

        @include('customer.bikes._form', ['bicycle' => null])

        <div class="mt-6 flex gap-3">
            <button type="submit" class="flex-1 rounded-lg bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                Save Bicycle
            </button>
            <a href="{{ route('customer.bikes.index') }}" class="rounded-lg bg-white px-5 py-3 text-sm font-semibold text-gray-900 border border-gray-300 hover:bg-gray-50">
                Cancel
            </a>
        </div>
    </form>
</x-app-layout>
