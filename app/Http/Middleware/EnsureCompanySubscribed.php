<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Read-only-Modus für Firmen ohne aktives Abo, Testzeitraum oder Billing-Befreiung:
 * Lese-Requests bleiben erlaubt, Schreib-Requests werden blockiert.
 */
class EnsureCompanySubscribed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isSuperAdmin()) {
            return $next($request);
        }

        $company = $user->company;

        if (! $company || $request->isMethodSafe() || $company->hasFullAccess()) {
            return $next($request);
        }

        $message = 'Ihr Testzeitraum ist abgelaufen oder es besteht kein aktives Abo. Daten können nur noch gelesen werden.';

        if ($user->isCompanyAdmin()) {
            return redirect()->route('billing.index')->with('error', $message);
        }

        return back()->with('error', $message.' Bitte wenden Sie sich an Ihren Administrator.');
    }
}
