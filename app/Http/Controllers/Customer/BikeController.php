<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\BicycleRequest;
use App\Models\Bicycle;
use App\Models\BicycleType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BikeController extends Controller
{
    public function index(Request $request): View
    {
        return view('customer.bikes.index', [
            'bicycles' => $request->user()->bicycles()->with('bicycleType')->latest()->get(),
        ]);
    }

    public function create(): View
    {
        return view('customer.bikes.create', [
            'bicycleTypes' => BicycleType::orderBy('sort_order')->get(),
        ]);
    }

    public function store(BicycleRequest $request): RedirectResponse
    {
        $bicycle = $request->user()->bicycles()->create($request->validated());

        return redirect()->route('customer.bikes.show', $bicycle)
            ->with('status', 'bicycle-created');
    }

    public function show(Bicycle $bicycle): View
    {
        $this->authorize('view', $bicycle);

        return view('customer.bikes.show', [
            'bicycle' => $bicycle->load('bicycleType'),
        ]);
    }

    public function edit(Bicycle $bicycle): View
    {
        $this->authorize('update', $bicycle);

        return view('customer.bikes.edit', [
            'bicycle' => $bicycle,
            'bicycleTypes' => BicycleType::orderBy('sort_order')->get(),
        ]);
    }

    public function update(BicycleRequest $request, Bicycle $bicycle): RedirectResponse
    {
        $this->authorize('update', $bicycle);

        $bicycle->update($request->validated());

        return redirect()->route('customer.bikes.show', $bicycle)
            ->with('status', 'bicycle-updated');
    }
}
