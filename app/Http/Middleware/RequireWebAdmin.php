<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireWebAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get(config('admin.session_key')) !== true) {
            return redirect()->guest(route('admin.login'));
        }

        return $next($request);
    }
}
