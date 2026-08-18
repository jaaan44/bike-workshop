<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * "Needs Attention" and "Workshop Today" counts come from bookings and
     * repair jobs, which land in Phases 3-5. Shown as zero-state for now.
     */
    public function index(Request $request): View
    {
        return view('staff.dashboard', [
            'user' => $request->user(),
        ]);
    }
}
