<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SchedulingSandboxScenario;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\SchedulingSandbox\SchedulingSandboxService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SchedulingLabController extends Controller
{
    public function __construct(
        private readonly SchedulingSandboxService $sandbox,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $run = $this->sandbox->activeRunFor($user);

        return Inertia::render('Admin/SchedulingLab/Index', [
            'enabled' => config('scheduling_lab.enabled'),
            'run' => $run ? $this->formatRun($run) : null,
            'inspector' => $run ? $this->sandbox->inspectorData($run) : null,
            'scenarios' => SchedulingSandboxScenario::options(),
            'companies' => Company::query()
                ->where('is_active', true)
                ->where('is_sandbox', false)
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
        ]);
    }

    public function setupScenario(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'scenario' => ['required', 'string'],
            'use_grok_live' => ['boolean'],
        ]);

        $scenario = SchedulingSandboxScenario::from($validated['scenario']);

        $this->sandbox->setupScenario(
            $request->user(),
            $scenario,
            $validated['use_grok_live'] ?? true,
        );

        return back()->with('success', 'Szenario wurde aufgesetzt.');
    }

    public function setupCompanySnapshot(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'use_grok_live' => ['boolean'],
            'mark_due_today' => ['boolean'],
            'anonymize_emails' => ['boolean'],
        ]);

        $source = Company::query()
            ->where('is_sandbox', false)
            ->findOrFail($validated['company_id']);

        $this->sandbox->setupCompanySnapshot(
            $request->user(),
            $source,
            $validated['use_grok_live'] ?? true,
            $validated['mark_due_today'] ?? false,
            $validated['anonymize_emails'] ?? true,
        );

        return back()->with('success', 'Firmen-Snapshot wurde erstellt.');
    }

    public function runScheduling(Request $request): RedirectResponse
    {
        $run = $this->sandbox->activeRunFor($request->user());

        if (! $run) {
            return back()->with('error', 'Bitte zuerst ein Szenario oder einen Snapshot aufsetzen.');
        }

        $this->sandbox->runScheduling($run);

        return back()->with('success', 'KI-Planung abgeschlossen. Prüfen Sie den Test-Posteingang.');
    }

    public function reset(Request $request): RedirectResponse
    {
        $this->sandbox->reset($request->user());

        return back()->with('success', 'Sandbox wurde zurückgesetzt.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRun(\App\Models\SchedulingSandboxRun $run): array
    {
        $run->load(['messages.proposal', 'sourceCompany']);

        return [
            'id' => $run->id,
            'mode' => $run->mode->value,
            'scenario' => $run->scenario?->value,
            'scenario_label' => $run->scenario?->label(),
            'status' => $run->status->value,
            'use_grok_live' => $run->use_grok_live,
            'snapshot_meta' => $run->snapshot_meta,
            'grok_debug' => $run->grok_debug,
            'validation_results' => $run->validation_results,
            'source_company' => $run->sourceCompany ? [
                'id' => $run->sourceCompany->id,
                'name' => $run->sourceCompany->name,
            ] : null,
            'company' => [
                'id' => $run->company->id,
                'name' => $run->company->name,
                'snapshot_at' => $run->company->sandbox_snapshot_at?->toIso8601String(),
            ],
            'messages' => $run->messages->sortByDesc('created_at')->values()->map(fn ($message) => [
                'id' => $message->id,
                'type' => $message->type->value,
                'subject' => $message->subject,
                'body_html' => $message->body_html,
                'meta' => $message->meta,
                'created_at' => $message->created_at?->toIso8601String(),
            ]),
        ];
    }
}
