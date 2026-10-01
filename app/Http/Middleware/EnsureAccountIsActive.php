<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->account_status?->blocksAccess()) {
            return response()->json(['message' => 'Account is not active.'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
