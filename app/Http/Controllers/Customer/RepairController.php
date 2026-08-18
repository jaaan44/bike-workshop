<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookingRequest;
use App\Models\BicyclePartCategory;
use App\Models\Booking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RepairController extends Controller
{
    public function index(Request $request): View
    {
        return view('customer.repairs.index', [
            'bookings' => $request->user()->bookings()->with('bicycle')->latest()->get(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('customer.repairs.create', [
            'bicycles' => $request->user()->bicycles,
            'categories' => BicyclePartCategory::with(['bicycleParts' => fn ($query) => $query->orderBy('sort_order')])
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function store(BookingRequest $request): RedirectResponse
    {
        $booking = $request->user()->bookings()->create($request->safe()->only([
            'bicycle_id', 'remarks', 'appointment_date',
        ]));

        $booking->bicycleParts()->attach($request->validated('bicycle_parts'));

        return redirect()->route('customer.repairs.show', $booking)
            ->with('status', 'booking-created');
    }

    public function show(Booking $booking): View
    {
        $this->authorize('view', $booking);

        return view('customer.repairs.show', [
            'booking' => $booking->load(['bicycle', 'bicycleParts.bicyclePartCategory']),
        ]);
    }
}
