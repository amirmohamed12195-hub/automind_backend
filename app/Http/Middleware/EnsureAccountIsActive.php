<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->suspended_at !== null) {
            $request->user()->currentAccessToken()?->delete();

            return ApiResponse::error('ACCOUNT_SUSPENDED', 'This account has been suspended. Contact support for help.', 403);
        }

        return $next($request);
    }
}
