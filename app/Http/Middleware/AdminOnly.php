<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Administrator-only areas (AdminSettings). Not signed in => login screen; signed in but not an admin => 403
 * (the user is NOT logged out, unlike the CI role redirect, so a stale link cannot kill an LCO's session).
 */
class AdminOnly
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->session()->has('usertype')) {
            return redirect('login/logout');
        }

        abort_unless((string) $request->session()->get('usertype') === '1', 403);

        return $next($request);
    }
}
