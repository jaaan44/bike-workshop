@php
    $partsByCategory = $booking->bicycleParts->groupBy(fn ($part) => $part->bicyclePartCategory->name);

    $shareableStatuses = [
        \App\Enums\BookingStatus::AwaitingCustomerApproval,
        \App\Enums\BookingStatus::RepairInProgress,
        \App\Enums\BookingStatus::RepairCompleted,
        \App\Enums\BookingStatus::QualityCheck,
        \App\Enums\BookingStatus::ReadyForPickup,
        \App\Enums\BookingStatus::ReadyForDelivery,
        \App\Enums\BookingStatus::Completed,
    ];
    // A booking closed after the customer declined the proposed repairs is
    // also shareable, so the declined proposal stays visible for reference
    // (not the ordinary Cancelled case, which never has inspection/repair
    // item data to show since cancel() only fires from Pending/Accepted).
    $isDeclinedAndClosed = $booking->wasCancelledAfterCustomerDecline();
    $isShareable = in_array($booking->status, $shareableStatuses, true) || $isDeclinedAndClosed;

    $history = $booking->statusHistories->sortBy('id')->values();

    // The timeline's first entry is synthetic: a booking is created directly
    // with status=pending (see Booking::booted()), so no status-history row
    // exists for the very first stage. Every later entry comes straight from
    // booking_status_histories, the app's one authoritative audit trail.
    $timeline = collect([
        (object) ['label' => 'Booking Submitted', 'timestamp' => $booking->created_at],
    ])->concat($history->map(fn ($entry) => (object) [
        'label' => $entry->new_status->label(),
        'timestamp' => $entry->created_at,
    ]));
    $currentIndex = $timeline->count() - 1;

    $approvalEntry = $history->last(fn ($entry) => $entry->old_status === \App\Enums\BookingStatus::AwaitingCustomerApproval
        && $entry->new_status === \App\Enums\BookingStatus::RepairInProgress);
    $repairCompletedEntry = $history->last(fn ($entry) => $entry->new_status === \App\Enums\BookingStatus::RepairCompleted);
    $fulfillmentEntry = $history->last(fn ($entry) => in_array($entry->new_status, [\App\Enums\BookingStatus::ReadyForPickup, \App\Enums\BookingStatus::ReadyForDelivery], true));
    $completedEntry = $history->last(fn ($entry) => $entry->new_status === \App\Enums\BookingStatus::Completed);
    $closedWithoutRepairEntry = $history->last(fn ($entry) => $entry->old_status === \App\Enums\BookingStatus::Inspection
        && $entry->new_status === \App\Enums\BookingStatus::Cancelled);

    $latestUpdate = optional($history->last())->created_at ?? $booking->created_at;
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$booking->reference_number" class="mb-0" />
    </x-slot>

    @if (session('status') === 'booking-created')
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
            Booking submitted &mdash; we'll review it and confirm your appointment.
        </div>
    @elseif (session('status') === 'repair-approved')
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
            Repairs approved &mdash; the workshop will get started.
        </div>
    @elseif (session('status') === 'repair-declined')
        <div class="mb-4 rounded-lg bg-gray-50 border border-gray-200 px-4 py-3 text-sm text-gray-700">
            Repairs declined. The workshop will follow up with you.
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <!-- Summary -->
    <div class="bg-white rounded-xl border border-gray-200 p-4 mb-4">
        <div class="flex items-start justify-between gap-2 mb-3">
            <div>
                <p class="font-semibold text-gray-900">{{ $booking->bicycle->nickname }}</p>
                <p class="text-sm text-gray-500">{{ $booking->bicycle->bicycleType->name }}</p>
            </div>
            <x-status-badge :status="$booking->status" />
        </div>
        <div class="text-sm text-gray-700 space-y-0.5">
            <p><span class="text-gray-500">Submitted:</span> {{ $booking->created_at->format('M j, Y') }}</p>
            <p><span class="text-gray-500">Appointment:</span> {{ $booking->appointment_date->format('l, M j, Y') }}</p>
            <p class="text-gray-500">Last update: {{ $latestUpdate->diffForHumans() }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-4 mb-4">
        <p class="text-xs font-semibold text-gray-500 uppercase mb-2">What Needs Attention</p>
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
    </div>

    @if ($booking->remarks)
        <div class="bg-white rounded-xl border border-gray-200 p-4 mb-4">
            <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Your Remarks</p>
            <p class="text-sm text-gray-900 whitespace-pre-line">{{ $booking->remarks }}</p>
        </div>
    @endif

    @if ($isShareable && $booking->inspection && ($booking->inspection->findings || $booking->inspection->recommended_repairs))
        <div class="bg-white rounded-xl border border-gray-200 p-4 mb-4">
            <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Inspection</p>

            @if ($booking->inspection->findings)
                <p class="text-xs font-medium text-gray-500 mb-1">Findings</p>
                <p class="text-sm text-gray-900 whitespace-pre-line mb-3">{{ $booking->inspection->findings }}</p>
            @endif

            @if ($booking->inspection->recommended_repairs)
                <p class="text-xs font-medium text-gray-500 mb-1">Recommended Work</p>
                <p class="text-sm text-gray-900 whitespace-pre-line">{{ $booking->inspection->recommended_repairs }}</p>
            @endif
        </div>
    @endif

    @if ($isShareable && $booking->repairItems->isNotEmpty())
        <div class="bg-white rounded-xl border border-gray-200 p-4 mb-4" x-data="">
            <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Proposed Repairs</p>

            <ul class="space-y-1 mb-3">
                @foreach ($booking->repairItems as $item)
                    <li class="text-sm text-gray-700 flex items-center gap-2">
                        @if ($item->isCompleted())
                            <span class="text-green-600" aria-hidden="true">&check;</span>
                            <span class="text-gray-400 line-through">{{ $item->description }}</span>
                            <span class="sr-only">(done)</span>
                        @else
                            <span aria-hidden="true">&bull;</span>
                            <span>{{ $item->description }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if ($booking->status === \App\Enums\BookingStatus::AwaitingCustomerApproval)
                <div class="grid grid-cols-2 gap-2 mt-3">
                    <form method="POST" action="{{ route('customer.repairs.approve', $booking) }}">
                        @csrf
                        <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                            Approve
                        </button>
                    </form>
                    <button type="button" x-on:click="$dispatch('open-modal', 'decline-repairs')"
                        class="w-full rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-red-600 border border-gray-300 hover:bg-gray-50">
                        Decline
                    </button>
                </div>

                <x-modal name="decline-repairs" focusable>
                    <form method="POST" action="{{ route('customer.repairs.decline', $booking) }}" class="p-6">
                        @csrf
                        <h2 class="text-lg font-medium text-gray-900">Decline these repairs?</h2>
                        <p class="mt-1 text-sm text-gray-600">
                            The workshop will be notified and will follow up with you before proceeding.
                        </p>
                        <div class="mt-6 flex justify-end gap-3">
                            <x-secondary-button type="button" x-on:click="$dispatch('close')">
                                Never Mind
                            </x-secondary-button>
                            <x-danger-button>Decline</x-danger-button>
                        </div>
                    </form>
                </x-modal>
            @elseif ($approvalEntry)
                <p class="text-xs text-gray-500 pt-2 border-t border-gray-100">
                    You approved these repairs on {{ $approvalEntry->created_at->format('M j, Y g:ia') }}.
                </p>
            @elseif ($isDeclinedAndClosed)
                <p class="text-xs text-gray-500 pt-2 border-t border-gray-100">
                    You declined these repairs. Your bicycle was returned without repair.
                </p>
            @endif
        </div>
    @endif

    @if ($booking->status === \App\Enums\BookingStatus::QualityCheck)
        <div class="mb-4 rounded-lg bg-purple-50 border border-purple-200 px-4 py-3 text-sm text-purple-700">
            Your repair is complete and is now going through a final quality check.
        </div>
    @elseif (in_array($booking->status, [\App\Enums\BookingStatus::ReadyForPickup, \App\Enums\BookingStatus::ReadyForDelivery], true))
        <div class="mb-4 rounded-lg bg-teal-50 border border-teal-200 px-4 py-3 text-sm text-teal-700">
            @if ($booking->status === \App\Enums\BookingStatus::ReadyForPickup)
                Your bicycle has passed quality check and is ready for pickup at the workshop.
            @else
                Your bicycle has passed quality check and is ready to be delivered to you.
            @endif
        </div>
    @elseif ($booking->status === \App\Enums\BookingStatus::Completed)
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
            This repair is complete
            @if ($fulfillmentEntry)
                &mdash; {{ $fulfillmentEntry->new_status === \App\Enums\BookingStatus::ReadyForPickup ? 'picked up' : 'delivered' }}
            @endif
            @if ($completedEntry)
                on {{ $completedEntry->created_at->format('M j, Y') }}
            @endif
            . Thanks for choosing us!
        </div>
    @elseif ($repairCompletedEntry && $booking->status === \App\Enums\BookingStatus::RepairCompleted)
        <div class="mb-4 rounded-lg bg-purple-50 border border-purple-200 px-4 py-3 text-sm text-purple-700">
            Repair work is finished and about to go through quality check.
        </div>
    @elseif ($booking->status === \App\Enums\BookingStatus::Cancelled)
        <div class="mb-4 rounded-lg bg-gray-50 border border-gray-200 px-4 py-3 text-sm text-gray-700">
            @if ($isDeclinedAndClosed)
                You declined the proposed repairs
                @if ($closedWithoutRepairEntry)
                    on {{ $closedWithoutRepairEntry->created_at->format('M j, Y') }}
                @endif
                . Your bicycle was returned without repair.
            @else
                This booking was cancelled.
            @endif
        </div>
    @endif

    <!-- Repair Timeline -->
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <p class="text-xs font-semibold text-gray-500 uppercase mb-3">Repair Timeline</p>
        <ol class="space-y-4">
            @foreach ($timeline as $index => $entry)
                @php $isCurrent = $index === $currentIndex; @endphp
                <li class="relative pl-6">
                    @if (! $loop->last)
                        <span class="absolute left-[5px] top-4 bottom-[-1rem] w-px bg-gray-200" aria-hidden="true"></span>
                    @endif
                    <span
                        class="absolute left-0 top-1 h-2.5 w-2.5 rounded-full {{ $isCurrent ? 'bg-indigo-600 ring-4 ring-indigo-100' : 'bg-green-500' }}"
                        aria-hidden="true"
                    ></span>
                    <p class="text-sm {{ $isCurrent ? 'font-semibold text-gray-900' : 'font-medium text-gray-700' }}">
                        {{ $entry->label }}
                        @if ($isCurrent)
                            <span class="ml-1 text-xs font-medium text-indigo-600">(Current)</span>
                        @endif
                    </p>
                    <p class="text-xs text-gray-400">{{ $entry->timestamp->format('M j, Y g:ia') }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</x-app-layout>
