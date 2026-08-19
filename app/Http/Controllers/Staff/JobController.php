<?php

namespace App\Http\Controllers\Staff;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JobController extends Controller
{
    /**
     * "Jobs" are bookings that have moved past acceptance into active
     * workshop work (bike received onward). Bookings still awaiting
     * review/acceptance live under "Bookings" instead.
     */
    public function index(Request $request): View
    {
        $viewer = $request->user();

        $jobs = Booking::with(['user', 'bicycle', 'assignedTechnician'])
            ->whereNotIn('status', [BookingStatus::Pending, BookingStatus::Accepted, BookingStatus::Cancelled])
            ->get()
            ->sortBy(fn (Booking $booking) => $viewer->isTechnician() && $booking->assigned_technician_id === $viewer->id ? 0 : 1)
            ->values();

        return view('staff.jobs.index', ['jobs' => $jobs]);
    }
}
