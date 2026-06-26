<?php

use App\Http\Controllers\Admin\BillingSettingsController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\Billing\SubscriptionController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerRecurringServiceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\NegotiationController;
use App\Http\Controllers\Public\ProposalResponseController;
use App\Http\Controllers\ServiceTypeController;
use App\Http\Controllers\StaffCalendarController;
use App\Http\Controllers\StaffMemberController;
use App\Http\Controllers\StaffWorkingHoursController;
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

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified', 'company'])
    ->name('dashboard');

Route::middleware(['auth', 'company', 'subscribed'])->group(function () {
    Route::resource('customers', CustomerController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('/customers/{customer}/recurring-services', [CustomerRecurringServiceController::class, 'store'])->name('customers.recurring-services.store');
    Route::patch('/customers/{customer}/recurring-services/{recurringService}', [CustomerRecurringServiceController::class, 'update'])->name('customers.recurring-services.update');
    Route::delete('/customers/{customer}/recurring-services/{recurringService}', [CustomerRecurringServiceController::class, 'destroy'])->name('customers.recurring-services.destroy');
    Route::resource('service-types', ServiceTypeController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('/staff', [StaffMemberController::class, 'index'])->name('staff.index');
    Route::post('/staff', [StaffMemberController::class, 'store'])->name('staff.store');
    Route::patch('/staff/{staffMember}/availability', [StaffMemberController::class, 'updateAvailability'])->name('staff.availability');
    Route::get('/working-hours', [StaffWorkingHoursController::class, 'index'])->name('working-hours.index');
    Route::patch('/working-hours', [StaffWorkingHoursController::class, 'update'])->name('working-hours.update');
    Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
    Route::post('/appointments/schedule', [AppointmentController::class, 'triggerScheduling'])->name('appointments.schedule');
    Route::get('/my-calendar', StaffCalendarController::class)->name('staff.calendar');
});

Route::middleware(['auth', 'company', 'subscribed', 'role:company_admin'])->prefix('prospects')->name('prospects.')->group(function () {
    Route::get('/', [\App\Http\Controllers\ProspectHubController::class, 'index'])->name('index');

    Route::middleware('prospect_search')->group(function () {
        Route::post('/profiles', [\App\Http\Controllers\ProspectSearchProfileController::class, 'store'])->name('profiles.store');
        Route::patch('/profiles/{profile}', [\App\Http\Controllers\ProspectSearchProfileController::class, 'update'])->name('profiles.update');
        Route::delete('/profiles/{profile}', [\App\Http\Controllers\ProspectSearchProfileController::class, 'destroy'])->name('profiles.destroy');
        Route::post('/profiles/{profile}/run', [\App\Http\Controllers\ProspectSearchProfileController::class, 'run'])->name('profiles.run');
        Route::patch('/{prospect}', [\App\Http\Controllers\CustomerProspectController::class, 'update'])->name('update');
        Route::delete('/{prospect}', [\App\Http\Controllers\CustomerProspectController::class, 'destroy'])->name('destroy');
        Route::post('/{prospect}/outreach', [\App\Http\Controllers\CustomerProspectController::class, 'outreach'])->name('outreach');
        Route::post('/{prospect}/convert', [\App\Http\Controllers\CustomerProspectController::class, 'convert'])->name('convert');
    });
});

// Billing-Routen bewusst ohne 'subscribed'-Middleware, damit sie ohne aktives Abo erreichbar bleiben.
Route::middleware(['auth', 'company', 'role:company_admin'])->prefix('billing')->name('billing.')->group(function () {
    Route::get('/', [SubscriptionController::class, 'index'])->name('index');
    Route::post('/checkout', [SubscriptionController::class, 'checkout'])->name('checkout');
    Route::post('/prospect-addon', [SubscriptionController::class, 'purchaseProspectAddon'])->name('prospect-addon');
    Route::get('/portal', [SubscriptionController::class, 'portal'])->name('portal');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'role:super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
    Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');
    Route::patch('/companies/{company}', [CompanyController::class, 'update'])->name('companies.update');

    Route::resource('plans', PlanController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('/coupons', [CouponController::class, 'index'])->name('coupons.index');
    Route::post('/coupons', [CouponController::class, 'store'])->name('coupons.store');
    Route::delete('/coupons/{couponId}', [CouponController::class, 'destroy'])->name('coupons.destroy');
    Route::patch('/promotion-codes/{promotionCodeId}/deactivate', [CouponController::class, 'deactivateCode'])->name('promotion-codes.deactivate');

    Route::patch('/billing-settings', [BillingSettingsController::class, 'update'])->name('billing-settings.update');
});

Route::prefix('p')->name('public.')->group(function () {
    Route::get('/proposals/{token}', [ProposalResponseController::class, 'show'])->name('proposals.show');
    Route::post('/proposals/{token}/accept', [ProposalResponseController::class, 'accept'])->name('proposals.accept');
    Route::post('/proposals/{token}/reject', [ProposalResponseController::class, 'reject'])->name('proposals.reject');
    Route::get('/negotiations/{token}', [NegotiationController::class, 'show'])->name('negotiations.show');
    Route::post('/negotiations/{token}', [NegotiationController::class, 'store'])->name('negotiations.store');
    Route::get('/prospect-opt-out/{token}', [\App\Http\Controllers\Public\ProspectOptOutController::class, 'show'])->name('prospect-opt-out.show');
    Route::post('/prospect-opt-out/{token}', [\App\Http\Controllers\Public\ProspectOptOutController::class, 'store'])->name('prospect-opt-out.store');
});

require __DIR__.'/auth.php';
