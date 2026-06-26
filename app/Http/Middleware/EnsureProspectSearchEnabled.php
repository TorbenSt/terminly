<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Schreibende Prospect-Aktionen nur mit freigeschalteter Kundensuche.
 * Lesezugriff/Upsell-Seite bleibt ohne Freischaltung möglich.
 */
class EnsureProspectSearchEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $company = $user?->company;

        if (! $user || $user->isSuperAdmin() || ! $company || $company->hasProspectSearchAccess()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Kundensuche ist nicht freigeschaltet.'], 403);
        }

        return redirect()
            ->route('prospects.index')
            ->with('error', 'Kundensuche ist nicht freigeschaltet. Bitte buchen Sie das Add-on oder upgraden Sie Ihr Abo.');
    }
}
