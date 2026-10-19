<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BillingException;
use App\Models\KashierOrder;
use App\Models\User;
use App\Services\Billing\EntitlementService;
use App\Services\Billing\KashierCheckoutService;
use App\Services\PlatformSettings;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KashierMobileController
{
    public function catalog(Request $request, KashierCheckoutService $checkout, EntitlementService $entitlements)
    {
        $this->platform($request);
        $user = $request->user();
        $available = $this->available($user, $checkout);
        $pending = KashierOrder::query()->where('user_id', $user->id)
            ->where('mode', config('kashier.mode'))->whereIn('status', ['creating', 'pending'])
            ->where('expires_at', '>', now())->latest()->first();

        return ApiResponse::success([
            'available' => $available, 'accountReference' => $user->id,
            'countryCode' => strtoupper($user->country_code ?? ''),
            'plans' => $available ? $checkout->catalog($user) : [],
            'hasSubscription' => $entitlements->snapshot($user)['access']['hasSubscription'],
            'pendingOrder' => $pending ? $this->payload($pending, $available) : null,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function create(Request $request, KashierCheckoutService $checkout)
    {
        $this->platform($request);
        if (! $this->available($request->user(), $checkout)) {
            throw new BillingException('KASHIER_NOT_AVAILABLE', 'Kashier is not available for this account.', 403);
        }
        $data = $request->validate(['plan' => ['required', 'string', 'max:64'], 'idempotencyKey' => ['required', 'uuid']]);
        $order = $checkout->create($request->user(), $data['plan'], $data['idempotencyKey']);

        return ApiResponse::success($this->payload($order, true))->header('Cache-Control', 'no-store, private');
    }

    public function show(Request $request, KashierOrder $order, KashierCheckoutService $checkout, EntitlementService $entitlements)
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        // Existing payments can still settle after checkout has been disabled or
        // the account country has changed. Ownership is checked before any I/O.
        if (! $order->last_checked_at || $order->last_checked_at->lt(now()->subSeconds(3))) {
            $order = $checkout->refresh($order);
        }

        return ApiResponse::success([
            ...$this->payload($order, $this->available($request->user(), $checkout)),
            'entitlements' => $entitlements->snapshot($request->user()),
        ])->header('Cache-Control', 'no-store, private');
    }

    private function platform(Request $request): void
    {
        $request->validate(['platform' => ['required', Rule::in(['android', 'ios'])]]);
    }

    private function available(User $user, KashierCheckoutService $checkout): bool
    {
        // Use the authenticated account's stored country, never a client-sent
        // country, UI language, SIM locale, or untrusted forwarding header.
        return $checkout->enabled() && config('kashier.mobile_enabled')
            && (bool) app(PlatformSettings::class)->get('kashier_enabled')
            && strtoupper($user->country_code ?? '') === 'EG';
    }

    /** @return array<string, mixed> */
    private function payload(KashierOrder $order, bool $available): array
    {
        return [
            'id' => $order->id, 'planCode' => $order->plan_code, 'mode' => $order->mode,
            'status' => $order->status, 'amount' => number_format($order->amount_minor / 100, 2, '.', ''),
            'currency' => $order->currency, 'expiresAt' => $order->expires_at?->toIso8601String(),
            'fulfilled' => $order->store_purchase_id !== null,
            'sessionId' => $available && $order->status === 'pending' && $order->expires_at?->isFuture()
                ? $order->session_id : null,
        ];
    }
}
