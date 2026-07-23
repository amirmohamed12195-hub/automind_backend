<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RecordRequestMetrics
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = hrtime(true);
        $response = $next($request);
        Log::info('api_request', ['request_id' => $request->attributes->get('request_id'), 'method' => $request->method(), 'route' => $request->route()?->uri(), 'status' => $response->getStatusCode(), 'latency_ms' => (int) ((hrtime(true) - $start) / 1_000_000), 'user_id_hash' => $request->user() ? hash_hmac('sha256', (string) $request->user()->id, (string) config('app.key')) : null]);

        return $response;
    }
}
