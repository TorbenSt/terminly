<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AppointmentProposal;
use App\Services\ProposalSchedulingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProposalResponseController extends Controller
{
    public function show(string $token): Response
    {
        $proposal = AppointmentProposal::where('token', $token)
            ->with(['appointment.customer', 'appointment.serviceType'])
            ->firstOrFail();

        return Inertia::render('Public/ProposalResponse', [
            'proposal' => [
                'token' => $proposal->token,
                'round' => $proposal->round,
                'options' => collect($proposal->options())->map(fn ($slot, $key) => [
                    'number' => $key,
                    'label' => $slot->timezone(config('app.timezone'))->format('d.m.Y H:i'),
                    'iso' => $slot->toIso8601String(),
                    'recommended' => $proposal->round > 1 && $key === ($proposal->recommended_option ?? 1),
                ])->values(),
                'service_name' => $proposal->appointment->serviceType->name,
                'duration_minutes' => $proposal->appointment->duration_minutes,
            ],
            'schedulingLab' => request()->boolean('scheduling_lab'),
        ]);
    }

    public function accept(Request $request, string $token, ProposalSchedulingService $service): RedirectResponse
    {
        $proposal = AppointmentProposal::where('token', $token)->firstOrFail();

        $validated = $request->validate([
            'option' => ['required', 'integer', 'in:1,2,3'],
        ]);

        $service->acceptProposal($proposal, (int) $validated['option']);

        $params = ['token' => $token];
        if ($request->boolean('scheduling_lab')) {
            $params['scheduling_lab'] = 1;
        }

        return redirect()->route('public.proposals.show', $params)
            ->with('success', 'Termin bestätigt. Vielen Dank!');
    }

    public function reject(Request $request, string $token, ProposalSchedulingService $service): RedirectResponse
    {
        $proposal = AppointmentProposal::where('token', $token)->firstOrFail();
        $negotiation = $service->rejectAllOptions($proposal);

        if ($negotiation->status->value === 'escalated') {
            $params = ['token' => $token];
            if ($request->boolean('scheduling_lab')) {
                $params['scheduling_lab'] = 1;
            }

            return redirect()->route('public.proposals.show', $params)
                ->with('success', 'Wir melden uns persönlich bei Ihnen.');
        }

        $params = ['token' => $negotiation->token];
        if ($request->boolean('scheduling_lab')) {
            $params['scheduling_lab'] = 1;
        }

        return redirect()->route('public.negotiations.show', $params);
    }
}
