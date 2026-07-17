<?php

namespace App\Http\Controllers;

use App\Models\ServiceType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceTypeController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', ServiceType::class);

        return Inertia::render('ServiceTypes/Index', [
            'serviceTypes' => ServiceType::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ServiceType::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'is_recurring' => ['boolean'],
            'interval_days' => ['nullable', 'integer', 'min:1'],
            'interval_months' => ['nullable', 'integer', 'min:1'],
            'completion_window_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'description' => ['nullable', 'string'],
        ]);

        $validated['completion_window_days'] = $validated['completion_window_days']
            ?? (int) config('scheduling.default_completion_window_days', 14);

        ServiceType::create($validated);

        return back()->with('success', 'Service angelegt.');
    }

    public function update(Request $request, ServiceType $serviceType): RedirectResponse
    {
        $this->authorize('update', $serviceType);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'is_recurring' => ['boolean'],
            'interval_days' => ['nullable', 'integer', 'min:1'],
            'interval_months' => ['nullable', 'integer', 'min:1'],
            'completion_window_days' => ['required', 'integer', 'min:1', 'max:365'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $serviceType->update($validated);

        return back()->with('success', 'Service aktualisiert.');
    }

    public function destroy(ServiceType $serviceType): RedirectResponse
    {
        $this->authorize('delete', $serviceType);

        if ($serviceType->recurringServices()->exists() || $serviceType->appointments()->exists()) {
            return back()->with('error', 'Serviceart wird noch verwendet und kann nicht gelöscht werden.');
        }

        $serviceType->staffMembers()->detach();
        $serviceType->delete();

        return back()->with('success', 'Serviceart gelöscht.');
    }
}
