<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Replicates the CI constructor check: no `usertype` in session => redirect login/logout.
 * Optional parameters restrict to roles, e.g. `legacy.session:1,2` or `legacy.session:3`
 * (deny by default; the old site did not enforce roles on most controllers, see MIGRATION_SPEC section 3).
 */
class LegacySession
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        if (! $request->session()->has('usertype')) {
            return redirect('login/logout');
        }

        if ($roles && ! in_array((string) $request->session()->get('usertype'), $roles, true)) {
            return redirect('login/logout');
        }

        return $next($request);
    }
}
