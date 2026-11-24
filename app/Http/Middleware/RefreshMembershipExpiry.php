<?php

namespace App\Http\Middleware;

use App\Services\MembershipProcessor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RefreshMembershipExpiry
{
    public function handle(Request $request, Closure $next): Response
    {
        app(MembershipProcessor::class)->expire();

        return $next($request);
    }
}
