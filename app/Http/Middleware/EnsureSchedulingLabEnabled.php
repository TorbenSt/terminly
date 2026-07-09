<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSchedulingLabEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('scheduling_lab.enabled')) {
            abort(404);
        }

        return $next($request);
    }
}
