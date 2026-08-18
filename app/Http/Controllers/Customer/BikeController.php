<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class BikeController extends Controller
{
    /**
     * Bicycle registration lands in Phase 2. This is a navigation
     * placeholder so the "Bikes" tab has somewhere to go in the meantime.
     */
    public function index(): View
    {
        return view('customer.bikes.index');
    }
}
