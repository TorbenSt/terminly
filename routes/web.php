<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', \App\Http\Controllers\DashboardController::class)
    ->middleware(['auth', 'verified', 'company'])
    ->name('dashboard');

Route::middleware(['auth', 'company'])->group(function () {
    Route::resource('customers', \App\Http\Controllers\CustomerController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('/customers/{customer}/recurring-services', [\App\Http\Controllers\CustomerRecurringServiceController::class, 'store'])->name('customers.recurring-services.store');
    Route::patch('/customers/{customer}/recurring-services/{recurringService}', [\App\Http\Controllers\CustomerRecurringServiceController::class, 'update'])->name('customers.recurring-services.update');
    Route::delete('/customers/{customer}/recurring-services/{recurringService}', [\App\Http\Controllers\CustomerRecurringServiceController::class, 'destroy'])->name('customers.recurring-services.destroy');
    Route::resource('service-types', \App\Http\Controllers\ServiceTypeController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('/staff', [\App\Http\Controllers\StaffMemberController::class, 'index'])->name('staff.index');
    Route::post('/staff', [\App\Http\Controllers\StaffMemberController::class, 'store'])->name('staff.store');
    Route::patch('/staff/{staffMember}/availability', [\App\Http\Controllers\StaffMemberController::class, 'updateAvailability'])->name('staff.availability');
    Route::get('/appointments', [\App\Http\Controllers\AppointmentController::class, 'index'])->name('appointments.index');
    Route::post('/appointments/schedule', [\App\Http\Controllers\AppointmentController::class, 'triggerScheduling'])->name('appointments.schedule');
    Route::get('/my-calendar', \App\Http\Controllers\StaffCalendarController::class)->name('staff.calendar');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'role:super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/companies', [\App\Http\Controllers\Admin\CompanyController::class, 'index'])->name('companies.index');
    Route::post('/companies', [\App\Http\Controllers\Admin\CompanyController::class, 'store'])->name('companies.store');
});

Route::prefix('p')->name('public.')->group(function () {
    Route::get('/proposals/{token}', [\App\Http\Controllers\Public\ProposalResponseController::class, 'show'])->name('proposals.show');
    Route::post('/proposals/{token}/accept', [\App\Http\Controllers\Public\ProposalResponseController::class, 'accept'])->name('proposals.accept');
    Route::post('/proposals/{token}/reject', [\App\Http\Controllers\Public\ProposalResponseController::class, 'reject'])->name('proposals.reject');
    Route::get('/negotiations/{token}', [\App\Http\Controllers\Public\NegotiationController::class, 'show'])->name('negotiations.show');
    Route::post('/negotiations/{token}', [\App\Http\Controllers\Public\NegotiationController::class, 'store'])->name('negotiations.store');
});

require __DIR__.'/auth.php';
