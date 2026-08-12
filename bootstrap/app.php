<?php

use App\Exceptions\BillingException;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\RecordRequestMetrics;
use App\Http\Middleware\RequireAdmin;
use App\Http\Middleware\RequireBillingPermission;
use App\Http\Middleware\RequirePlatformFeature;
use App\Http\Middleware\RequireWebAdmin;
use App\Http\Middleware\SetApiLocale;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [AssignRequestId::class, SetApiLocale::class, RecordRequestMetrics::class]);
        $middleware->validateCsrfTokens(except: ['callbacks/sign_in_with_apple']);
        $middleware->alias([
            'admin' => RequireAdmin::class,
            'billing-permission' => RequireBillingPermission::class,
            'account-active' => EnsureAccountIsActive::class,
            'platform-feature' => RequirePlatformFeature::class,
            'web-admin' => RequireWebAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*'));
        $exceptions->render(function (BillingException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->errorCode, $e->getMessage(), $e->httpStatus, ['retryable' => $e->retryable]);
            }
        });
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('VALIDATION_FAILED', __('api.validation_failed'), 422, $e->errors());
            }
        });
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('UNAUTHENTICATED', __('api.unauthenticated'), 401);
            }
        });
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('FORBIDDEN', __('api.forbidden'), 403);
            }
        });
        $exceptions->render(function (DomainException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('INVALID_STATE', __('api.invalid_transition'), 409);
            }
        });
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('NOT_FOUND', __('api.not_found'), 404);
            }
        });
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($request->is('api/*')) {
                $status = $e->getStatusCode();
                $code = match ($status) {
                    404 => 'NOT_FOUND', 429 => 'RATE_LIMITED', 503 => 'SERVICE_UNAVAILABLE', default => 'HTTP_ERROR'
                };
                $message = match ($status) {
                    404 => __('api.not_found'), 429 => __('api.rate_limited'), 503 => __('api.service_unavailable'), default => __('api.generic_error'),
                };

                return ApiResponse::error($code, $message, $status);
            }
        });
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('INTERNAL_ERROR', __('api.generic_error'), 500);
            }
        });
    })->create();
