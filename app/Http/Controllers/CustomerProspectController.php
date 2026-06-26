<?php

namespace App\Http\Controllers;

use App\Enums\ProspectFeedbackAction;
use App\Enums\ProspectStatus;
use App\Jobs\SendProspectOutreachJob;
use App\Models\Customer;
use App\Models\CustomerProspect;
use App\Services\Prospect\ProspectFeedbackService;
use App\Services\Prospect\ProspectOutreachLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerProspectController extends Controller
{
    public function __construct(
        protected ProspectFeedbackService $feedback,
        protected ProspectOutreachLimitService $outreachLimits,
    ) {}

    public function update(Request $request, CustomerProspect $prospect): RedirectResponse
    {
        $this->authorize('update', $prospect);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(ProspectStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'feedback_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $prospect->update([
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? $prospect->notes,
        ]);

        $action = match ($validated['status']) {
            ProspectStatus::Converted->value => ProspectFeedbackAction::Converted,
            ProspectStatus::Rejected->value, ProspectStatus::Discarded->value => ProspectFeedbackAction::Rejected,
            default => ProspectFeedbackAction::Accepted,
        };

        if (in_array($validated['status'], [ProspectStatus::Rejected->value, ProspectStatus::Discarded->value, ProspectStatus::Interested->value], true)) {
            $this->feedback->record(
                $request->user()->company,
                $action,
                $prospect,
                $prospect->run,
                $validated['feedback_reason'] ?? null,
            );
        }

        return back()->with('success', 'Prospect aktualisiert.');
    }

    public function destroy(CustomerProspect $prospect): RedirectResponse
    {
        $this->authorize('delete', $prospect);
        $prospect->delete();

        return back()->with('success', 'Prospect gelöscht.');
    }

    public function outreach(Request $request, CustomerProspect $prospect): RedirectResponse
    {
        $this->authorize('update', $prospect);

        if (! $prospect->canReceiveOutreach()) {
            return back()->with('error', 'Für diesen Prospect kann keine E-Mail versendet werden.');
        }

        $company = $request->user()->company;

        if (! $this->outreachLimits->canSend($company)) {
            $limit = $this->outreachLimits->dailyLimit($company);

            return back()->with('error', "Tageslimit für Kaltakquise erreicht ({$limit} E-Mails/Tag).");
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        SendProspectOutreachJob::dispatch($prospect->id, $validated['subject'], $validated['body']);

        return back()->with('success', 'Kaltakquise-E-Mail wird versendet.');
    }

    public function convert(Request $request, CustomerProspect $prospect): RedirectResponse
    {
        $this->authorize('update', $prospect);

        if ($prospect->status === ProspectStatus::Converted) {
            return back()->with('error', 'Prospect wurde bereits übernommen.');
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:10'],
            'city' => ['required', 'string', 'max:100'],
        ]);

        $customer = Customer::create([
            'name' => $validated['name'] ?? $prospect->company_name,
            'email' => $prospect->email,
            'phone' => $prospect->phone,
            'address' => $validated['address'],
            'postal_code' => $validated['postal_code'],
            'city' => $validated['city'],
            'latitude' => $prospect->latitude,
            'longitude' => $prospect->longitude,
            'google_place_id' => $prospect->google_place_id,
            'notes' => $prospect->notes,
        ]);

        $prospect->update([
            'status' => ProspectStatus::Converted,
            'converted_customer_id' => $customer->id,
        ]);

        $this->feedback->record(
            $request->user()->company,
            ProspectFeedbackAction::Converted,
            $prospect,
            $prospect->run,
        );

        return back()->with('success', 'Prospect als Kunde übernommen.');
    }
}
