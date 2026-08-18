<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class RepairController extends Controller
{
    /**
     * Repair booking lands in Phase 3. This is a navigation placeholder
     * so the "Repairs" tab and "Book Repair" action have somewhere to go
     * in the meantime.
     */
    public function index(): View
    {
        return view('customer.repairs.index');
    }
}
