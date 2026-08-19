<?php

namespace App\Http\Controllers\Staff;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $counts = Booking::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $countFor = fn (BookingStatus $status) => $counts[$status->value] ?? 0;

        return view('staff.dashboard', [
            'user' => $request->user(),
            'newBookingsCount' => $countFor(BookingStatus::Pending),
            'acceptedCount' => $countFor(BookingStatus::Accepted),
            'bikeReceivedCount' => $countFor(BookingStatus::BikeReceived),
            'activeBookings' => Booking::with(['user', 'bicycle'])
                ->whereIn('status', [BookingStatus::Accepted, BookingStatus::BikeReceived])
                ->orderBy('appointment_date')
                ->get(),
        ]);
    }
}
