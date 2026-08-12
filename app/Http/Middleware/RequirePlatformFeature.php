<?php

namespace App\Http\Middleware;

use App\Services\PlatformSettings;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePlatformFeature
{
    public function __construct(private readonly PlatformSettings $settings) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if ($this->settings->get($feature.'_enabled') !== true) {
            return ApiResponse::error('FEATURE_UNAVAILABLE', 'This feature is temporarily unavailable.', 503);
        }

        return $next($request);
    }
}
