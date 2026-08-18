<?php

use App\Http\Controllers\Customer\BikeController;
use App\Http\Controllers\Customer\HomeController as CustomerHomeController;
use App\Http\Controllers\Customer\RepairController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Staff\BookingController;
use App\Http\Controllers\Staff\DashboardController as StaffDashboardController;
use App\Http\Controllers\Staff\JobController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Generic post-login landing spot: sends each role to its own home.
Route::get('/dashboard', function () {
    return redirect()->route(
        auth()->user()->isWorkshopUser() ? 'staff.dashboard' : 'customer.home'
    );
})->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'role:customer'])->prefix('customer')->name('customer.')->group(function () {
    Route::get('/home', [CustomerHomeController::class, 'index'])->name('home');
    Route::get('/bikes', [BikeController::class, 'index'])->name('bikes.index');
    Route::get('/repairs', [RepairController::class, 'index'])->name('repairs.index');
});

Route::middleware(['auth', 'role:staff,technician'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/dashboard', [StaffDashboardController::class, 'index'])->name('dashboard');
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');
});

require __DIR__.'/auth.php';
