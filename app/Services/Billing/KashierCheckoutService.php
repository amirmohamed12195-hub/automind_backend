<?php

namespace App\Services\Billing;

use App\Exceptions\BillingException;
use App\Models\BillingAccount;
use App\Models\BillingPlan;
use App\Models\KashierOrder;
use App\Models\StorePurchase;
use App\Models\User;
use App\Models\UserEntitlement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class KashierCheckoutService
{
    public function __construct(
        private readonly KashierClient $client,
        private readonly BillingAccountService $accounts,
        private readonly CreditLedgerService $credits,
        private readonly EntitlementService $entitlements,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('billing.enabled') && (bool) config('kashier.enabled');
    }

    /** @return array<int, array<string, mixed>> */
    public function catalog(User $user): array
    {
        return $this->plans($user)->map(function (BillingPlan $plan): array {
            $copy = $plan->localizations->firstWhere('locale', app()->getLocale())
                ?? $plan->localizations->firstWhere('locale', 'en');
            $minor = $this->minorUnits(config('kashier.prices.'.$plan->code));

            return [
                'code' => $plan->code, 'name' => $copy->display_name ?? $plan->code,
                'features' => $copy->feature_copy_json ?? [],
                'amount' => $minor === null ? null : $this->amount($minor),
                'currency' => 'EGP', 'available' => $this->enabled() && $minor !== null,
            ];
        })->values()->all();
    }

    public function create(User $user, string $planCode, string $idempotencyKey): KashierOrder
    {
        if (! $this->enabled()) {
            throw new BillingException('KASHIER_DISABLED', 'Website checkout is temporarily unavailable.', 503);
        }
        $account = $this->accounts->forUser($user);
        $order = DB::transaction(function () use ($user, $account, $planCode, $idempotencyKey): KashierOrder {
            BillingAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $existing = KashierOrder::query()->where('user_id', $user->id)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->plan_code !== $planCode || $existing->mode !== config('kashier.mode')) {
                    throw new BillingException('CHECKOUT_CONFLICT', 'This checkout request was already used for another order.', 409);
                }

                return $existing;
            }
            $plan = $this->plans($user)->firstWhere('code', $planCode);
            $minor = $this->minorUnits(config('kashier.prices.'.$planCode));
            if (! $plan || $minor === null) {
                throw new BillingException('PLAN_UNAVAILABLE', 'This plan is not available for website checkout.');
            }
            if ($plan->type === 'subscription' && $this->entitlements->snapshot($user)['access']['hasSubscription']) {
                throw new BillingException('SUBSCRIPTION_ALREADY_ACTIVE', 'You already have an active plan. Please wait until it expires before purchasing another.', 409);
            }
            $pending = KashierOrder::query()->where('user_id', $user->id)->where('mode', config('kashier.mode'))
                ->whereIn('status', ['creating', 'pending'])->where('expires_at', '>', now())->first();
            if ($pending) {
                if ($pending->plan_code === $planCode) {
                    return $pending;
                }
                throw new BillingException('CHECKOUT_ALREADY_OPEN', 'You have an unfinished checkout. Complete it or wait for it to expire before choosing another plan.', 409);
            }

            return KashierOrder::query()->create([
                'user_id' => $user->id, 'billing_plan_id' => $plan->id, 'plan_code' => $planCode,
                'idempotency_key' => $idempotencyKey, 'mode' => config('kashier.mode'),
                'amount_minor' => $minor, 'currency' => 'EGP', 'status' => 'creating',
                'expires_at' => now()->addMinutes((int) config('kashier.session_minutes')),
            ]);
        }, 3);
        if (! $order->wasRecentlyCreated) {
            return $order;
        }
        try {
            $session = $this->client->createSession([
                'expireAt' => $order->expires_at->toIso8601String(), 'maxFailureAttempts' => 3,
                'paymentType' => 'credit', 'amount' => $this->amount($order->amount_minor),
                'currency' => $order->currency, 'order' => $order->id,
                'merchantId' => config('kashier.merchant_id'),
                'merchantRedirect' => route('billing.return', ['order' => $order->id]),
                'serverWebhook' => url('/api/v1/billing/webhooks/kashier'),
                'display' => app()->getLocale() === 'ar' ? 'ar' : 'en',
                'type' => 'one-time', 'allowedMethods' => config('kashier.allowed_methods'),
                'manualCapture' => false, 'failureRedirect' => false,
                'customer' => ['email' => $user->email, 'reference' => $user->id],
            ]);
            $url = $session['sessionUrl'] ?? null;
            if (! is_string($session['_id'] ?? null) || $session['_id'] === '' || ! is_string($url)
                || parse_url($url, PHP_URL_SCHEME) !== 'https'
                || parse_url($url, PHP_URL_HOST) !== 'payments.kashier.io'
                || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PORT) !== null) {
                throw new BillingException('KASHIER_INVALID_RESPONSE', 'The payment provider returned an invalid checkout. Please contact support.', 503);
            }
            $order->update(['session_id' => $session['_id'], 'checkout_url' => $url, 'status' => 'pending']);
        } catch (BillingException $exception) {
            $order->update(['status' => 'creation_failed']);
            throw $exception;
        }

        return $order->fresh();
    }

    public function refresh(KashierOrder $order): KashierOrder
    {
        if (! $order->session_id || $order->mode !== config('kashier.mode')) {
            return $order;
        }
        $session = $this->client->session($order->session_id);
        $params = $session['paymentParams'] ?? [];
        // Never trust the browser redirect or unsigned event type. The provider's
        // session must match our stored order, merchant, price, and currency.
        if (($session['_id'] ?? null) !== $order->session_id
            || ($session['merchantId'] ?? null) !== config('kashier.merchant_id')
            || ($params['order'] ?? null) !== $order->id
            || ($params['currency'] ?? null) !== $order->currency
            || $this->minorUnits($params['amount'] ?? null) !== $order->amount_minor) {
            throw new BillingException('KASHIER_ORDER_MISMATCH', 'The payment details could not be verified. Please contact support.', 409);
        }
        $captured = $this->minorUnits($session['capturedAmount'] ?? null, true);
        $refunded = $this->minorUnits($session['refundedAmount'] ?? null, true);
        $state = $session['status'] ?? '';
        $paid = $state === 'PAID' && $captured === $order->amount_minor && $refunded === 0;
        $fullRefund = $captured === $order->amount_minor && $refunded === $order->amount_minor;
        $reversed = in_array($state, ['VOIDED', 'REVERSED'], true);
        $partialRefund = $refunded !== null && $refunded > 0 && $refunded < $order->amount_minor;

        $account = $order->user ? $this->accounts->forUser($order->user) : null;

        return DB::transaction(function () use ($order, $account, $paid, $fullRefund, $reversed, $partialRefund, $state): KashierOrder {
            if ($account) {
                BillingAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            }
            $locked = KashierOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $locked->last_checked_at = now();
            if ($fullRefund || $reversed) {
                $locked->status = 'refunded';
                $this->revoke($locked);
            } elseif ($partialRefund && $locked->status !== 'refunded') {
                // Partial refunds require an operator decision; never regrant.
                $locked->status = 'partially_refunded';
            } elseif ($paid && ! in_array($locked->status, ['paid', 'refunded', 'partially_refunded'], true)) {
                $locked->status = 'paid';
                $locked->paid_at ??= now();
                $this->fulfill($locked);
            } elseif (! in_array($locked->status, ['paid', 'refunded', 'partially_refunded'], true)) {
                $locked->status = match ($state) {
                    'FAILED', 'REJECTED' => 'failed',
                    'EXPIRED', 'ABANDONED' => 'expired',
                    default => 'pending',
                };
            }
            $locked->save();

            return $locked;
        }, 3);
    }

    /** @param array<string, mixed> $payload */
    public function webhook(array $payload, string $signature): void
    {
        $data = $payload['data'] ?? null;
        if (! is_array($data) || ! $this->client->verifyWebhook($data, $signature)) {
            throw new BillingException('INVALID_WEBHOOK_SIGNATURE', 'The webhook signature is invalid.', 401);
        }
        $order = KashierOrder::query()->whereKey($data['merchantOrderId'])->where('mode', config('kashier.mode'))->first();
        if (! $order) {
            return;
        }
        if (! $order->session_id) {
            throw new BillingException('CHECKOUT_NOT_READY', 'Checkout is still being created.', 503, true);
        }
        $this->refresh($order);
    }

    private function fulfill(KashierOrder $order): void
    {
        // Test payments must never grant real access on the production website.
        if (! $order->user || ($order->mode === 'test' && app()->environment('production'))) {
            return;
        }
        $starts = $order->paid_at->copy();
        if ($order->plan->type === 'subscription') {
            $active = $this->entitlements->activeSubscription($order->user);
            if ($active?->period_end?->isFuture()) {
                $starts = $active->period_end->copy();
            }
            $order->access_ends_at = $order->plan_code === 'PLUS_YEARLY'
                ? $starts->copy()->addYearNoOverflow() : $starts->copy()->addMonthNoOverflow();
        }
        $purchase = StorePurchase::query()->create([
            'user_id' => $order->user_id, 'platform' => 'kashier',
            'environment' => $order->mode === 'live' ? 'production' : 'sandbox',
            'product_id' => $order->plan_code, 'transaction_id' => $order->session_id,
            'order_id' => $order->id, 'state' => 'active', 'acknowledged' => true, 'consumed' => true,
            'purchased_at' => $order->paid_at, 'expires_at' => $order->access_ends_at,
            'last_verified_at' => now(), 'raw_reference' => ['verificationSource' => 'kashier_session'],
        ]);
        $order->store_purchase_id = $purchase->id;
        if ($order->plan->type === 'consumable') {
            $this->credits->grantPurchase($order->user, $purchase);
        } else {
            UserEntitlement::query()->create([
                'user_id' => $order->user_id, 'billing_plan_id' => $order->billing_plan_id,
                'store_purchase_id' => $purchase->id, 'entitlement_key' => 'kashier:'.$order->id,
                'source' => 'kashier', 'platform' => 'kashier', 'status' => 'active',
                'purchase_date' => $order->paid_at, 'period_start' => $starts,
                'period_end' => $order->access_ends_at, 'auto_renew_enabled' => false,
                'last_verified_at' => now(), 'verification_source' => 'kashier_session',
            ]);
        }
    }

    private function revoke(KashierOrder $order): void
    {
        $purchase = $order->store_purchase_id ? StorePurchase::query()->find($order->store_purchase_id) : null;
        if (! $purchase) {
            return;
        }
        $purchase->update(['state' => 'refunded', 'last_verified_at' => now()]);
        UserEntitlement::query()->where('store_purchase_id', $purchase->id)
            ->update(['status' => 'refunded', 'refunded_at' => now(), 'auto_renew_enabled' => false]);
        if ($order->user && $order->plan->type === 'consumable'
            && ! $this->credits->revokeUnusedPurchaseLocked($order->user, $purchase)) {
            $purchase->update(['raw_reference' => ['verificationSource' => 'kashier_session', 'refundNeedsReview' => true]]);
        }
    }

    /** @return Collection<int, BillingPlan> */
    private function plans(User $user): Collection
    {
        return BillingPlan::query()->whereIn('code', array_keys(config('kashier.prices')))
            ->where('active', true)->where('published', true)
            ->whereHas('regions', fn ($q) => $q->where('country_code', strtoupper($user->country_code ?: 'EG'))->where('visible', true)
                ->where(fn ($w) => $w->whereNull('available_from')->orWhere('available_from', '<=', now()))
                ->where(fn ($w) => $w->whereNull('available_until')->orWhere('available_until', '>', now())))
            ->with('localizations')->orderBy('sort_order')->get();
    }

    public function minorUnits(mixed $amount, bool $allowZero = false): ?int
    {
        if (! is_string($amount) && ! is_int($amount) && ! is_float($amount)) {
            return null;
        }
        if (! preg_match('/\A\d{1,7}(?:\.\d{1,2})?\z/', (string) $amount)) {
            return null;
        }
        $parts = explode('.', (string) $amount);
        $minor = ((int) $parts[0] * 100) + (int) str_pad($parts[1] ?? '', 2, '0');

        return $minor > 0 || $allowZero ? $minor : null;
    }

    private function amount(int $minor): string
    {
        return intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }
}
