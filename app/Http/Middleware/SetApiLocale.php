<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $accepted = strtolower(substr((string) $request->getPreferredLanguage(['en', 'ar']), 0, 2));
        $user = $request->user();
        $userLocale = $user instanceof User ? $user->locale : null;
        $locale = in_array($accepted, ['en', 'ar'], true)
            ? $accepted
            : ($userLocale ?? 'en');

        app()->setLocale(in_array($locale, ['en', 'ar'], true) ? $locale : 'en');

        return $next($request);
    }
}
