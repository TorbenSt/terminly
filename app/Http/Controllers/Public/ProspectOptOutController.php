<?php

namespace App\Http\Controllers\Public;

use App\Enums\ProspectStatus;
use App\Http\Controllers\Controller;
use App\Models\CustomerProspect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProspectOptOutController extends Controller
{
    public function show(string $token): Response
    {
        $prospect = CustomerProspect::where('opt_out_token', $token)->firstOrFail();

        return Inertia::render('Public/ProspectOptOut', [
            'companyName' => $prospect->company->name,
            'token' => $token,
            'alreadyOptedOut' => $prospect->status === ProspectStatus::OptedOut,
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $prospect = CustomerProspect::where('opt_out_token', $token)->firstOrFail();

        $prospect->update(['status' => ProspectStatus::OptedOut]);

        return back()->with('success', 'Sie erhalten keine weiteren Nachrichten von uns.');
    }
}
