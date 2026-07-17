<?php

namespace App\Http\Controllers;

use App\Enums\StaffCustomerBinding;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanySettingsController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->isCompanyAdmin() && $user->company_id, 403);

        /** @var Company $company */
        $company = $user->company;

        $validated = $request->validate([
            'staff_customer_binding' => ['required', Rule::enum(StaffCustomerBinding::class)],
        ]);

        $company->update($validated);

        return back()->with('success', 'Planungseinstellungen gespeichert.');
    }
}
