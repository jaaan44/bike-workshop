<?php

namespace App\Http\Controllers\Staff;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\RepairItem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BookingController extends Controller
{
    /**
     * Priority used to sort the incoming-bookings list: bookings that
     * still need staff action float to the top.
     */
    private const STATUS_PRIORITY = [
        'pending' => 0,
        'accepted' => 1,
        'scheduled' => 2,
        'bike_received' => 3,
        'inspection' => 3,
        'cancelled' => 4,
    ];

    public function index(): View
    {
        $bookings = Booking::with(['user', 'bicycle'])
            ->get()
            ->sortBy(fn (Booking $booking) => [
                self::STATUS_PRIORITY[$booking->status->value] ?? 99,
                $booking->appointment_date,
            ])
            ->values();

        return view('staff.bookings.index', ['bookings' => $bookings]);
    }

    public function show(Booking $booking): View
    {
        return view('staff.bookings.show', [
            'booking' => $booking->load([
                'user', 'bicycle.bicycleType', 'bicycleParts.bicyclePartCategory', 'statusHistories.changedBy',
                'assignedTechnician', 'inspection', 'repairItems.addedBy', 'technicianNotes.user',
            ]),
            'technicians' => User::where('role', UserRole::Technician)->orderBy('name')->get(),
        ]);
    }

    public function accept(Booking $booking): RedirectResponse
    {
        if ($booking->status !== BookingStatus::Pending) {
            return back()->with('error', 'This booking has already been reviewed.');
        }

        $booking->transitionTo(BookingStatus::Accepted);

        return back()->with('status', 'booking-accepted');
    }

    public function receive(Booking $booking): RedirectResponse
    {
        if ($booking->status !== BookingStatus::Accepted) {
            return back()->with('error', 'This booking needs to be accepted before the bike can be received.');
        }

        $booking->transitionTo(BookingStatus::BikeReceived);

        return back()->with('status', 'bike-received');
    }

    public function cancel(Booking $booking): RedirectResponse
    {
        if (! in_array($booking->status, [BookingStatus::Pending, BookingStatus::Accepted], true)) {
            return back()->with('error', 'This booking can no longer be cancelled.');
        }

        $booking->transitionTo(BookingStatus::Cancelled);

        return back()->with('status', 'booking-cancelled');
    }

    public function assignTechnician(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate([
            'assigned_technician_id' => ['nullable', Rule::exists('users', 'id')->where('role', UserRole::Technician->value)],
        ]);

        // assigned_technician_id is deliberately excluded from Booking's
        // Fillable (it must never be settable via the customer-facing
        // booking store() path), so it's set directly here instead of
        // through update().
        $booking->assigned_technician_id = $data['assigned_technician_id'];
        $booking->save();

        return back()->with('status', 'technician-assigned');
    }

    public function updateInspection(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate([
            'findings' => ['nullable', 'string', 'max:2000'],
            'recommended_repairs' => ['nullable', 'string', 'max:2000'],
        ]);

        $booking->inspection()->updateOrCreate([], [
            ...$data,
            'inspected_by' => $request->user()->id,
        ]);

        if ($booking->status === BookingStatus::BikeReceived) {
            $booking->transitionTo(BookingStatus::Inspection);
        }

        return back()->with('status', 'inspection-saved');
    }

    public function storeRepairItem(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
        ]);

        $booking->repairItems()->create([
            ...$data,
            'added_by' => $request->user()->id,
        ]);

        return back()->with('status', 'repair-item-added');
    }

    public function completeRepairItem(Booking $booking, RepairItem $repairItem): RedirectResponse
    {
        abort_unless($repairItem->booking_id === $booking->id, 404);

        // completed_at is deliberately excluded from RepairItem's Fillable,
        // set directly here instead of through update().
        $repairItem->completed_at = now();
        $repairItem->save();

        return back()->with('status', 'repair-item-completed');
    }

    public function storeTechnicianNote(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $booking->technicianNotes()->create([
            ...$data,
            'user_id' => $request->user()->id,
        ]);

        return back()->with('status', 'note-added');
    }

    public function requestApproval(Booking $booking): RedirectResponse
    {
        if ($booking->status !== BookingStatus::Inspection) {
            return back()->with('error', 'This booking is not ready to be sent for approval.');
        }

        if ($booking->repairItems->isEmpty()) {
            return back()->with('error', 'Add at least one repair item before requesting customer approval.');
        }

        $booking->transitionTo(BookingStatus::AwaitingCustomerApproval);

        return back()->with('status', 'approval-requested');
    }

    public function completeRepairs(Booking $booking): RedirectResponse
    {
        if ($booking->status !== BookingStatus::RepairInProgress) {
            return back()->with('error', 'This booking is not currently in repair.');
        }

        if ($booking->repairItems->isEmpty() || $booking->repairItems->contains(fn ($item) => ! $item->isCompleted())) {
            return back()->with('error', 'Mark all repair items as done before completing the repair.');
        }

        $booking->transitionTo(BookingStatus::RepairCompleted);

        return back()->with('status', 'repairs-completed');
    }

    public function startQualityCheck(Booking $booking): RedirectResponse
    {
        if ($booking->status !== BookingStatus::RepairCompleted) {
            return back()->with('error', 'This booking is not ready for quality check.');
        }

        $booking->transitionTo(BookingStatus::QualityCheck);

        return back()->with('status', 'quality-check-started');
    }

    public function passQualityCheck(Request $request, Booking $booking): RedirectResponse
    {
        if ($booking->status !== BookingStatus::QualityCheck) {
            return back()->with('error', 'This booking is not currently in quality check.');
        }

        $data = $request->validate([
            'fulfillment_method' => ['required', Rule::in(['pickup', 'delivery'])],
        ]);

        $booking->transitionTo(
            $data['fulfillment_method'] === 'pickup' ? BookingStatus::ReadyForPickup : BookingStatus::ReadyForDelivery
        );

        return back()->with('status', 'quality-check-passed');
    }

    public function failQualityCheck(Booking $booking): RedirectResponse
    {
        if ($booking->status !== BookingStatus::QualityCheck) {
            return back()->with('error', 'This booking is not currently in quality check.');
        }

        $booking->transitionTo(BookingStatus::RepairInProgress);

        return back()->with('status', 'quality-check-failed');
    }

    public function fulfill(Booking $booking): RedirectResponse
    {
        if (! in_array($booking->status, [BookingStatus::ReadyForPickup, BookingStatus::ReadyForDelivery], true)) {
            return back()->with('error', 'This booking is not ready to be completed.');
        }

        $booking->transitionTo(BookingStatus::Completed);

        return back()->with('status', 'booking-completed');
    }
}
