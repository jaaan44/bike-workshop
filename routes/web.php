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
    Route::get('/bikes/create', [BikeController::class, 'create'])->name('bikes.create');
    Route::post('/bikes', [BikeController::class, 'store'])->name('bikes.store');
    Route::get('/bikes/{bicycle}', [BikeController::class, 'show'])->name('bikes.show');
    Route::get('/bikes/{bicycle}/edit', [BikeController::class, 'edit'])->name('bikes.edit');
    Route::put('/bikes/{bicycle}', [BikeController::class, 'update'])->name('bikes.update');

    Route::get('/repairs', [RepairController::class, 'index'])->name('repairs.index');
    Route::get('/repairs/create', [RepairController::class, 'create'])->name('repairs.create');
    Route::post('/repairs', [RepairController::class, 'store'])->name('repairs.store');
    Route::get('/repairs/{booking}', [RepairController::class, 'show'])->name('repairs.show');
    Route::post('/repairs/{booking}/approve', [RepairController::class, 'approve'])->name('repairs.approve');
    Route::post('/repairs/{booking}/decline', [RepairController::class, 'decline'])->name('repairs.decline');
});

Route::middleware(['auth', 'role:staff,technician'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/dashboard', [StaffDashboardController::class, 'index'])->name('dashboard');
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
    Route::post('/bookings/{booking}/accept', [BookingController::class, 'accept'])->name('bookings.accept');
    Route::post('/bookings/{booking}/receive', [BookingController::class, 'receive'])->name('bookings.receive');
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
    Route::put('/bookings/{booking}/technician', [BookingController::class, 'assignTechnician'])->name('bookings.technician.update');
    Route::put('/bookings/{booking}/inspection', [BookingController::class, 'updateInspection'])->name('bookings.inspection.update');
    Route::post('/bookings/{booking}/repair-items', [BookingController::class, 'storeRepairItem'])->name('bookings.repair-items.store');
    Route::post('/bookings/{booking}/repair-items/{repairItem}/complete', [BookingController::class, 'completeRepairItem'])->name('bookings.repair-items.complete');
    Route::post('/bookings/{booking}/notes', [BookingController::class, 'storeTechnicianNote'])->name('bookings.notes.store');
    Route::post('/bookings/{booking}/request-approval', [BookingController::class, 'requestApproval'])->name('bookings.approval.request');
    Route::post('/bookings/{booking}/repairs/complete', [BookingController::class, 'completeRepairs'])->name('bookings.repairs.complete');
    Route::post('/bookings/{booking}/quality-check/start', [BookingController::class, 'startQualityCheck'])->name('bookings.quality-check.start');
    Route::post('/bookings/{booking}/quality-check/pass', [BookingController::class, 'passQualityCheck'])->name('bookings.quality-check.pass');
    Route::post('/bookings/{booking}/quality-check/fail', [BookingController::class, 'failQualityCheck'])->name('bookings.quality-check.fail');
    Route::post('/bookings/{booking}/fulfill', [BookingController::class, 'fulfill'])->name('bookings.fulfill');

    Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');
});

require __DIR__.'/auth.php';
