<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class JobController extends Controller
{
    /**
     * Repair job management lands in Phase 5. Navigation placeholder.
     */
    public function index(): View
    {
        return view('staff.jobs.index');
    }
}
