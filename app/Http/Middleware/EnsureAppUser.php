<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Only owner and staff may use the app; customers have no screens in v1. */
class EnsureAppUser
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->role?->canUseApp(), 403);

        return $next($request);
    }
}
