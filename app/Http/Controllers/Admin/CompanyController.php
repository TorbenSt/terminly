<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Company::class);

        return Inertia::render('Admin/Companies/Index', [
            'companies' => Company::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Company::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'timezone' => ['required', 'string', 'max:64'],
        ]);

        Company::create([
            ...$validated,
            'slug' => Str::slug($validated['name']).'-'.Str::random(4),
        ]);

        return back()->with('success', 'Unternehmen angelegt.');
    }
}
