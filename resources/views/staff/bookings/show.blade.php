@php
    $partsByCategory = $booking->bicycleParts->groupBy(fn ($part) => $part->bicyclePartCategory->name);
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$booking->reference_number" class="mb-0" />
    </x-slot>

    @if (session('status') === 'booking-accepted')
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">Booking accepted.</div>
    @elseif (session('status') === 'bike-received')
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">Bike marked as received.</div>
    @elseif (session('status') === 'booking-cancelled')
        <div class="mb-4 rounded-lg bg-gray-50 border border-gray-200 px-4 py-3 text-sm text-gray-700">Booking cancelled.</div>
    @elseif (session('status') === 'technician-assigned')
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">Technician assignment saved.</div>
    @elseif (session('status') === 'inspection-saved')
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">Inspection saved.</div>
    @elseif (session('status') === 'repair-item-added')
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">Repair item added.</div>
    @elseif (session('status') === 'repair-item-completed')
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">Repair item marked done.</div>
    @elseif (session('status') === 'note-added')
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">Note added.</div>
    @endif

    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 p-4 mb-4">
        <div class="flex items-start justify-between gap-2 mb-3">
            <div>
                <p class="font-semibold text-gray-900">{{ $booking->bicycle->nickname }}</p>
                <p class="text-sm text-gray-500">{{ $booking->bicycle->bicycleType->name }}</p>
            </div>
            <x-status-badge :status="$booking->status" />
        </div>
        <div class="text-sm text-gray-700 space-y-0.5">
            <p><span class="text-gray-500">Customer:</span> {{ $booking->user->name }}</p>
            @if ($booking->user->phone)
                <p><span class="text-gray-500">Phone:</span> {{ $booking->user->phone }}</p>
            @endif
            <p><span class="text-gray-500">Appointment:</span> {{ $booking->appointment_date->format('l, M j, Y') }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-4 mb-4">
        <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Customer Reported Issues</p>
        <div class="space-y-3">
            @foreach ($partsByCategory as $categoryName => $parts)
                <div>
                    <p class="text-sm font-medium text-gray-700">{{ $categoryName }}</p>
                    <div class="flex flex-wrap gap-1.5 mt-1">
                        @foreach ($parts as $part)
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs text-gray-700">
                                {{ $part->name }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        @if ($booking->remarks)
            <div class="mt-4 pt-4 border-t border-gray-100">
                <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Customer Remarks</p>
                <p class="text-sm text-gray-900 whitespace-pre-line">&ldquo;{{ $booking->remarks }}&rdquo;</p>
            </div>
        @endif
    </div>

    <!-- Actions -->
    <div class="space-y-3 mb-4" x-data="">
        @if ($booking->status === \App\Enums\BookingStatus::Pending)
            <form method="POST" action="{{ route('staff.bookings.accept', $booking) }}">
                @csrf
                <button type="submit" class="w-full rounded-lg bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                    Accept Booking
                </button>
            </form>
        @elseif ($booking->status === \App\Enums\BookingStatus::Accepted)
            <form method="POST" action="{{ route('staff.bookings.receive', $booking) }}">
                @csrf
                <button type="submit" class="w-full rounded-lg bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                    Receive Bicycle
                </button>
            </form>
        @endif

        @if (in_array($booking->status, [\App\Enums\BookingStatus::Pending, \App\Enums\BookingStatus::Accepted], true))
            <button type="button" x-on:click="$dispatch('open-modal', 'cancel-booking')"
                class="w-full rounded-lg bg-white px-5 py-3 text-sm font-semibold text-red-600 border border-gray-300 hover:bg-gray-50">
                Cancel Booking
            </button>

            <x-modal name="cancel-booking" focusable>
                <form method="POST" action="{{ route('staff.bookings.cancel', $booking) }}" class="p-6">
                    @csrf
                    <h2 class="text-lg font-medium text-gray-900">Cancel this booking?</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        The customer will see this booking as cancelled. This can't be undone.
                    </p>
                    <div class="mt-6 flex justify-end gap-3">
                        <x-secondary-button type="button" x-on:click="$dispatch('close')">
                            Never Mind
                        </x-secondary-button>
                        <x-danger-button>Cancel Booking</x-danger-button>
                    </div>
                </form>
            </x-modal>
        @endif
    </div>

    @if (! in_array($booking->status, [\App\Enums\BookingStatus::Pending, \App\Enums\BookingStatus::Accepted, \App\Enums\BookingStatus::Cancelled], true))
        <!-- Technician Assignment -->
        <div class="bg-white rounded-xl border border-gray-200 p-4 mb-4">
            <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Assigned Technician</p>
            <form method="POST" action="{{ route('staff.bookings.technician.update', $booking) }}" class="flex gap-2">
                @csrf
                @method('PUT')
                <select name="assigned_technician_id" class="flex-1 rounded-lg border-gray-300 text-sm">
                    <option value="">Unassigned</option>
                    @foreach ($technicians as $technician)
                        <option value="{{ $technician->id }}" @selected($booking->assigned_technician_id === $technician->id)>
                            {{ $technician->name }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                    Save
                </button>
            </form>
        </div>

        <!-- Inspection -->
        <div class="bg-white rounded-xl border border-gray-200 p-4 mb-4">
            <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Inspection</p>
            <form method="POST" action="{{ route('staff.bookings.inspection.update', $booking) }}" class="space-y-3">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Findings</label>
                    <textarea name="findings" rows="3" class="w-full rounded-lg border-gray-300 text-sm" placeholder="What did you find during inspection?">{{ old('findings', $booking->inspection?->findings) }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Recommended Repairs</label>
                    <textarea name="recommended_repairs" rows="3" class="w-full rounded-lg border-gray-300 text-sm" placeholder="What repairs do you recommend?">{{ old('recommended_repairs', $booking->inspection?->recommended_repairs) }}</textarea>
                </div>
                <button type="submit" class="w-full rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                    Save Inspection
                </button>
            </form>
            @if ($booking->inspection?->inspectedBy)
                <p class="mt-2 text-xs text-gray-400">Last updated by {{ $booking->inspection->inspectedBy->name }}</p>
            @endif
        </div>

        <!-- Repair Items -->
        <div class="bg-white rounded-xl border border-gray-200 p-4 mb-4">
            <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Repair Items</p>

            @if ($booking->repairItems->isEmpty())
                <p class="text-sm text-gray-500 mb-3">No repair items added yet.</p>
            @else
                <ul class="space-y-2 mb-3">
                    @foreach ($booking->repairItems as $item)
                        <li class="flex items-center justify-between gap-2 text-sm">
                            <span class="{{ $item->isCompleted() ? 'text-gray-400 line-through' : 'text-gray-900' }}">
                                {{ $item->description }}
                            </span>
                            @if (! $item->isCompleted())
                                <form method="POST" action="{{ route('staff.bookings.repair-items.complete', [$booking, $item]) }}">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 whitespace-nowrap">
                                        Mark Done
                                    </button>
                                </form>
                            @else
                                <span class="text-xs text-green-600 font-medium whitespace-nowrap">Done</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('staff.bookings.repair-items.store', $booking) }}" class="flex gap-2">
                @csrf
                <input type="text" name="description" placeholder="Add a repair item" class="flex-1 rounded-lg border-gray-300 text-sm" required maxlength="255">
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                    Add
                </button>
            </form>
        </div>

        <!-- Technician Notes -->
        <div class="bg-white rounded-xl border border-gray-200 p-4 mb-4">
            <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Technician Notes</p>

            @if ($booking->technicianNotes->isEmpty())
                <p class="text-sm text-gray-500 mb-3">No notes yet.</p>
            @else
                <ul class="space-y-3 mb-3">
                    @foreach ($booking->technicianNotes as $note)
                        <li class="text-sm">
                            <p class="text-gray-900 whitespace-pre-line">{{ $note->note }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $note->user->name }} &middot; {{ $note->created_at->format('M j, Y g:ia') }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('staff.bookings.notes.store', $booking) }}" class="space-y-2">
                @csrf
                <textarea name="note" rows="2" class="w-full rounded-lg border-gray-300 text-sm" placeholder="Add a note" required maxlength="2000"></textarea>
                <button type="submit" class="w-full rounded-lg bg-white px-5 py-2 text-sm font-semibold text-gray-700 border border-gray-300 hover:bg-gray-50">
                    Add Note
                </button>
            </form>
        </div>
    @endif

    <!-- Status history -->
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <p class="text-xs font-semibold text-gray-500 uppercase mb-3">History</p>
        <ul class="space-y-2">
            <li class="text-sm text-gray-500">
                <span class="text-gray-900 font-medium">Booking Received</span>
                &middot; {{ $booking->created_at->format('M j, Y g:ia') }}
            </li>
            @foreach ($booking->statusHistories->sortBy('id') as $history)
                <li class="text-sm text-gray-500">
                    <span class="text-gray-900 font-medium">{{ $history->new_status->label() }}</span>
                    @if ($history->changedBy)
                        by {{ $history->changedBy->name }}
                    @endif
                    &middot; {{ $history->created_at->format('M j, Y g:ia') }}
                </li>
            @endforeach
        </ul>
    </div>
</x-app-layout>
