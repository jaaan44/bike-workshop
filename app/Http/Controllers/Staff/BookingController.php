<?php

namespace App\Http\Controllers\Staff;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\RedirectResponse;
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
            ]),
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
}
