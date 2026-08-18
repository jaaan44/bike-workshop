<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class BookingController extends Controller
{
    /**
     * Incoming booking management lands in Phase 4. Navigation placeholder.
     */
    public function index(): View
    {
        return view('staff.bookings.index');
    }
}
