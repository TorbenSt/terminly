<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\RecurringService;
use App\Models\StaffMember;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return Inertia::render('Dashboard', [
                'stats' => ['mode' => 'super_admin'],
            ]);
        }

        $companyId = $user->company_id;

        return Inertia::render('Dashboard', [
            'stats' => [
                'due_recurring' => RecurringService::where('company_id', $companyId)
                    ->where('is_active', true)
                    ->where('next_due_at', '<=', now())
                    ->count(),
                'open_negotiations' => Appointment::where('company_id', $companyId)
                    ->where('status', AppointmentStatus::Negotiation)
                    ->count(),
                'confirmed_today' => Appointment::where('company_id', $companyId)
                    ->where('status', AppointmentStatus::Confirmed)
                    ->whereDate('scheduled_at', today())
                    ->count(),
                'active_staff' => StaffMember::where('company_id', $companyId)->where('is_active', true)->count(),
            ],
            'recentAppointments' => Appointment::with(['customer', 'serviceType', 'staffMember'])
                ->where('company_id', $companyId)
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'customer' => $a->customer->name,
                    'service' => $a->serviceType->name,
                    'status' => $a->status->value,
                    'scheduled_at' => $a->scheduled_at?->toIso8601String(),
                    'staff' => $a->staffMember?->name,
                ]),
        ]);
    }
}
