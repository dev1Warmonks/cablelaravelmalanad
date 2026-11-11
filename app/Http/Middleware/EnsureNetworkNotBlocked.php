<?php

namespace App\Http\Middleware;

use App\Services\NetworkBlockService;
use Closure;
use Illuminate\Http\Request;

/**
 * Master controller constructor behaviour: if block_network() says "true" the user is logged out.
 * (Transaction/monthly_bills is deliberately NOT behind this, it is the payment screen for blocked LCOs.)
 */
class EnsureNetworkNotBlocked
{
    public function __construct(private NetworkBlockService $block)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        if ($this->block->blockNetwork($request->session()->get('userid')) === 'true') {
            return redirect('login/logout');
        }

        return $next($request);
    }
}
