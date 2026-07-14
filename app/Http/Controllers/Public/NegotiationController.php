<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AppointmentNegotiation;
use App\Services\ProposalSchedulingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NegotiationController extends Controller
{
    public function show(string $token): Response
    {
        $negotiation = AppointmentNegotiation::where('token', $token)
            ->with(['appointment.serviceType'])
            ->firstOrFail();

        return Inertia::render('Public/NegotiationForm', [
            'negotiation' => [
                'token' => $negotiation->token,
                'round' => $negotiation->round,
                'service_name' => $negotiation->appointment->serviceType->name,
            ],
            'schedulingLab' => request()->boolean('scheduling_lab'),
        ]);
    }

    public function store(Request $request, string $token, ProposalSchedulingService $service): RedirectResponse
    {
        $negotiation = AppointmentNegotiation::where('token', $token)->firstOrFail();

        $validated = $request->validate([
            'feedback' => ['required', 'string', 'min:10', 'max:2000'],
            'request_manual_contact' => ['sometimes', 'boolean'],
        ]);

        if ($validated['request_manual_contact'] ?? false) {
            $service->escalateToManualNegotiation(
                $negotiation->appointment,
                $validated['feedback']
            );

            return back()->with('success', 'Vielen Dank. Ein Mitarbeiter meldet sich bei Ihnen.');
        }

        $service->submitNegotiationFeedback($negotiation, $validated['feedback']);

        return back()->with('success', 'Vielen Dank! Wir senden Ihnen neue Terminvorschläge per E-Mail.');
    }
}
