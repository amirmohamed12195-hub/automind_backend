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
        $accepted = null;
        foreach ($request->getLanguages() as $language) {
            $candidate = strtolower(substr($language, 0, 2));
            if (in_array($candidate, ['en', 'ar'], true)) {
                $accepted = $candidate;
                break;
            }
        }
        $user = $request->user() ?? $request->user('sanctum');
        $userLocale = $user instanceof User ? $user->locale : null;
        $locale = in_array($accepted, ['en', 'ar'], true)
            ? $accepted
            : ($userLocale ?? 'en');

        app()->setLocale(in_array($locale, ['en', 'ar'], true) ? $locale : 'en');

        return $next($request);
    }
}
