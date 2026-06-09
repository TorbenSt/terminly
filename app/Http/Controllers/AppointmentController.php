<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSchedulingJob;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\ServiceType;
use App\Models\StaffMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Appointment::class);

        return Inertia::render('Appointments/Index', [
            'appointments' => Appointment::with(['customer', 'serviceType', 'staffMember', 'proposals'])
                ->orderByDesc('created_at')
                ->paginate(20),
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'serviceTypes' => ServiceType::orderBy('name')->get(['id', 'name', 'duration_minutes']),
            'staffMembers' => StaffMember::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function triggerScheduling(Request $request): RedirectResponse
    {
        $companyId = $request->user()->company_id;
        ProcessSchedulingJob::dispatch($companyId);

        return back()->with('success', 'KI-Planung wurde gestartet.');
    }
}
