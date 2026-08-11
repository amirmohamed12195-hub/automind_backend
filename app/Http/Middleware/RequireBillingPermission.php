<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireBillingPermission
{
    /** @var array<string, array<int, string>> */
    private const PERMISSIONS = [
        'billing.view' => ['SUPER_ADMIN', 'BILLING_ADMIN', 'SUPPORT_AGENT', 'ANALYST', 'AUDITOR'],
        'billing.catalog.manage' => ['SUPER_ADMIN', 'BILLING_ADMIN'],
        'billing.entitlements.manage' => ['SUPER_ADMIN', 'BILLING_ADMIN', 'SUPPORT_AGENT'],
        'billing.credits.adjust' => ['SUPER_ADMIN', 'BILLING_ADMIN', 'SUPPORT_AGENT'],
        'billing.events.reprocess' => ['SUPER_ADMIN', 'BILLING_ADMIN'],
        'billing.audit.view' => ['SUPER_ADMIN', 'BILLING_ADMIN', 'ANALYST', 'AUDITOR'],
    ];

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        $role = strtoupper((string) ($user?->admin_role ?: ($user?->is_admin ? 'SUPER_ADMIN' : '')));
        if (! $user?->is_admin || ! in_array($role, self::PERMISSIONS[$permission] ?? [], true)) {
            return ApiResponse::error('FORBIDDEN', __('api.forbidden'), 403);
        }

        return $next($request);
    }
}
