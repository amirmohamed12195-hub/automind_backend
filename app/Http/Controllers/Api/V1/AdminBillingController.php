<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\GooglePlayProvider;
use App\Jobs\ProcessBillingEvent;
use App\Jobs\ReconcileUserBilling;
use App\Models\BillingAdminAuditLog;
use App\Models\BillingEvent;
use App\Models\BillingPlan;
use App\Models\CreditLedgerEntry;
use App\Models\ManualEntitlementGrant;
use App\Models\StorePriceSnapshot;
use App\Models\StoreProduct;
use App\Models\StorePurchase;
use App\Models\User;
use App\Models\UserEntitlement;
use App\Services\Billing\BillingAdminAuditService;
use App\Services\Billing\CreditLedgerService;
use App\Services\Billing\EntitlementService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminBillingController
{
    public function overview()
    {
        return ApiResponse::success([
            'activeSubscriptions' => UserEntitlement::query()->whereIn('status', ['active', 'gracePeriod', 'canceledActiveUntilExpiry'])->where('period_end', '>', now())->count(),
            'graceOrRetrySubscriptions' => UserEntitlement::query()->whereIn('status', ['gracePeriod', 'billingRetry'])->count(),
            'unprocessedEvents' => BillingEvent::query()->whereNotIn('processing_status', ['processed', 'ignored'])->count(),
            'pendingStoreCompletion' => StorePurchase::query()->where('platform', 'google')->where(fn ($q) => $q->where('acknowledged', false)->orWhere('consumed', false))->count(),
            'creditsOutstanding' => (int) CreditLedgerEntry::query()->whereIn('id', CreditLedgerEntry::query()->selectRaw('MAX(id)')->groupBy('user_id'))->sum('balance_after'),
            'storeProductsPending' => StoreProduct::query()->where(fn ($q) => $q->where('store_status', '!=', 'active')->orWhere('active_for_sale', false))->count(),
        ]);
    }

    public function plans()
    {
        $plans = BillingPlan::query()->with(['localizations', 'features', 'regions', 'storeProducts'])->orderBy('sort_order')->get();

        return ApiResponse::success($plans);
    }

    public function createPlan(Request $request, BillingAdminAuditService $audit)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', 'regex:/^[A-Z][A-Z0-9_]*$/', 'unique:billing_plans,code'],
            'type' => ['required', Rule::in(['free', 'consumable', 'subscription'])],
            'sortOrder' => ['nullable', 'integer', 'between:0,10000'],
            'maxVehicles' => ['nullable', 'integer', 'between:1,1000'],
            'reportsPerPeriod' => ['nullable', 'integer', 'between:1,1000'],
        ]);
        $plan = BillingPlan::query()->create([
            'code' => $data['code'], 'type' => $data['type'], 'active' => false, 'published' => false,
            'sort_order' => $data['sortOrder'] ?? 0, 'recommended' => false, 'default_for_new_users' => false,
            'max_vehicles' => $data['maxVehicles'] ?? null, 'reports_per_period' => $data['reportsPerPeriod'] ?? null,
        ]);
        $audit->record($request, 'billing.plan.created', $plan, null, $plan->toArray());

        return ApiResponse::success($plan, 201);
    }

    public function showPlan(BillingPlan $plan)
    {
        return ApiResponse::success($plan->load(['localizations', 'features', 'regions', 'storeProducts.priceSnapshots']));
    }

    public function setPlanActivation(Request $request, BillingPlan $plan, BillingAdminAuditService $audit, bool $active)
    {
        $before = $plan->toArray();
        $plan->update(['active' => $active, 'published' => $active ? $plan->published : false]);
        $audit->record($request, $active ? 'billing.plan.activated' : 'billing.plan.deactivated', $plan, $before, $plan->fresh()->toArray());

        return ApiResponse::success($plan->fresh());
    }

    public function activatePlan(Request $request, BillingPlan $plan, BillingAdminAuditService $audit)
    {
        return $this->setPlanActivation($request, $plan, $audit, true);
    }

    public function deactivatePlan(Request $request, BillingPlan $plan, BillingAdminAuditService $audit)
    {
        return $this->setPlanActivation($request, $plan, $audit, false);
    }

    public function duplicatePlan(Request $request, BillingPlan $plan, BillingAdminAuditService $audit)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:64', 'regex:/^[A-Z][A-Z0-9_]*$/', 'unique:billing_plans,code']]);
        $copy = DB::transaction(function () use ($plan, $data): BillingPlan {
            $plan->load(['localizations', 'features', 'regions']);
            $copy = BillingPlan::query()->create([
                ...$plan->only(['type', 'sort_order', 'max_vehicles', 'reports_per_period']),
                'code' => $data['code'], 'active' => false, 'published' => false,
                'recommended' => false, 'default_for_new_users' => false,
            ]);
            foreach ($plan->localizations as $localization) {
                $copy->localizations()->create($localization->only(['locale', 'display_name', 'short_description', 'full_description', 'badge_text', 'feature_copy_json']));
            }
            foreach ($plan->features as $feature) {
                $copy->features()->create($feature->only(['feature_key', 'enabled', 'limit_value', 'configuration_json']));
            }
            foreach ($plan->regions as $region) {
                $copy->regions()->create($region->only(['country_code', 'visible', 'available_from', 'available_until', 'paywall_variant']));
            }

            return $copy;
        });
        $audit->record($request, 'billing.plan.duplicated', $copy, ['sourcePlanId' => $plan->id], $copy->toArray());

        return ApiResponse::success($copy->load(['localizations', 'features', 'regions']), 201);
    }

    public function updatePlan(Request $request, BillingPlan $plan, BillingAdminAuditService $audit)
    {
        $data = $request->validate([
            'active' => ['sometimes', 'boolean'], 'published' => ['sometimes', 'boolean'],
            'recommended' => ['sometimes', 'boolean'], 'sortOrder' => ['sometimes', 'integer', 'between:0,10000'],
            'maxVehicles' => ['sometimes', 'nullable', 'integer', 'between:1,1000'],
            'reportsPerPeriod' => ['sometimes', 'nullable', 'integer', 'between:1,1000'],
            'localizations' => ['sometimes', 'array'], 'localizations.*.locale' => ['required', Rule::in(['en', 'ar'])],
            'localizations.*.displayName' => ['required', 'string', 'max:120'],
            'localizations.*.shortDescription' => ['nullable', 'string', 'max:255'],
            'localizations.*.fullDescription' => ['nullable', 'string', 'max:5000'],
            'localizations.*.badgeText' => ['nullable', 'string', 'max:80'],
            'localizations.*.featureCopy' => ['nullable', 'array', 'max:30'],
            'features' => ['sometimes', 'array'], 'features.*.key' => ['required', 'string', 'max:100'],
            'features.*.enabled' => ['required', 'boolean'], 'features.*.limit' => ['nullable', 'integer', 'min:0'],
            'regions' => ['sometimes', 'array'], 'regions.*.countryCode' => ['required', Rule::in(['EG', 'SA', 'AE'])],
            'regions.*.visible' => ['required', 'boolean'], 'regions.*.availableFrom' => ['nullable', 'date'],
            'regions.*.availableUntil' => ['nullable', 'date', 'after:regions.*.availableFrom'],
        ]);
        $before = $plan->load(['localizations', 'features', 'regions'])->toArray();
        DB::transaction(function () use ($plan, $data): void {
            $updates = [];
            foreach (['active' => 'active', 'published' => 'published', 'recommended' => 'recommended', 'sortOrder' => 'sort_order', 'maxVehicles' => 'max_vehicles', 'reportsPerPeriod' => 'reports_per_period'] as $input => $column) {
                if (array_key_exists($input, $data)) {
                    $updates[$column] = $data[$input];
                }
            }
            $plan->update($updates);
            foreach ($data['localizations'] ?? [] as $copy) {
                $plan->localizations()->updateOrCreate(['locale' => $copy['locale']], [
                    'display_name' => $copy['displayName'], 'short_description' => $copy['shortDescription'] ?? null,
                    'full_description' => $copy['fullDescription'] ?? null, 'badge_text' => $copy['badgeText'] ?? null,
                    'feature_copy_json' => $copy['featureCopy'] ?? [],
                ]);
            }
            foreach ($data['features'] ?? [] as $feature) {
                $plan->features()->updateOrCreate(['feature_key' => $feature['key']], [
                    'enabled' => $feature['enabled'], 'limit_value' => $feature['limit'] ?? null,
                ]);
            }
            foreach ($data['regions'] ?? [] as $region) {
                $plan->regions()->updateOrCreate(['country_code' => $region['countryCode']], [
                    'visible' => $region['visible'], 'available_from' => $region['availableFrom'] ?? null,
                    'available_until' => $region['availableUntil'] ?? null,
                ]);
            }
        });
        $fresh = $plan->fresh(['localizations', 'features', 'regions']);
        $audit->record($request, 'billing.plan.updated', $plan, $before, $fresh->toArray());

        return ApiResponse::success($fresh);
    }

    public function products(Request $request)
    {
        $data = $request->validate(['platform' => ['nullable', Rule::in(['apple', 'google'])], 'environment' => ['nullable', Rule::in(['sandbox', 'production'])]]);
        $products = StoreProduct::query()->with(['plan', 'priceSnapshots' => fn ($q) => $q->latest('fetched_at')->limit(20)])
            ->when($data['platform'] ?? null, fn ($q, $value) => $q->where('platform', $value))
            ->when($data['environment'] ?? null, fn ($q, $value) => $q->where('environment', $value))
            ->orderBy('platform')->orderBy('product_id')->get();

        return ApiResponse::success($products);
    }

    public function createProduct(Request $request, BillingAdminAuditService $audit)
    {
        $data = $request->validate([
            'planId' => ['required', 'ulid', 'exists:billing_plans,id'],
            'platform' => ['required', Rule::in(['apple', 'google'])],
            'environment' => ['required', Rule::in(['sandbox', 'production'])],
            'productId' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9._-]+$/'],
            'productType' => ['required', Rule::in(['consumable', 'subscription'])],
            'basePlanId' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9._-]+$/'],
            'offerId' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9._-]+$/'],
            'effectiveFrom' => ['nullable', 'date'], 'effectiveUntil' => ['nullable', 'date', 'after:effectiveFrom'],
        ]);
        if ($data['platform'] === 'google' && $data['productType'] === 'subscription' && empty($data['basePlanId'])) {
            return ApiResponse::error('BASE_PLAN_REQUIRED', 'Google subscriptions require a base plan ID.', 422);
        }
        $mappingKey = implode(':', [$data['platform'], $data['environment'], $data['productId'], $data['basePlanId'] ?? '-', $data['offerId'] ?? '-']);
        if (StoreProduct::query()->where('mapping_key', $mappingKey)->exists()) {
            return ApiResponse::error('STORE_PRODUCT_ALREADY_EXISTS', 'This store mapping already exists.', 409);
        }
        $product = StoreProduct::query()->create([
            'billing_plan_id' => $data['planId'], 'mapping_key' => $mappingKey,
            'platform' => $data['platform'], 'environment' => $data['environment'],
            'product_id' => $data['productId'], 'product_type' => $data['productType'],
            'base_plan_id' => $data['basePlanId'] ?? null, 'offer_id' => $data['offerId'] ?? null,
            'active_for_sale' => false, 'store_status' => 'pending',
            'effective_from' => $data['effectiveFrom'] ?? null, 'effective_until' => $data['effectiveUntil'] ?? null,
        ]);
        $audit->record($request, 'billing.product.created', $product, null, $product->toArray());

        return ApiResponse::success($product->load('plan'), 201);
    }

    public function updateProduct(Request $request, StoreProduct $product, BillingAdminAuditService $audit)
    {
        $data = $request->validate([
            'activeForSale' => ['sometimes', 'boolean'],
            'storeStatus' => ['sometimes', Rule::in(['pending', 'active', 'rejected', 'retired'])],
            'effectiveFrom' => ['sometimes', 'nullable', 'date'], 'effectiveUntil' => ['sometimes', 'nullable', 'date', 'after:effectiveFrom'],
        ]);
        if (($data['activeForSale'] ?? false) && (($data['storeStatus'] ?? $product->store_status) !== 'active')) {
            return ApiResponse::error('STORE_PRODUCT_NOT_ACTIVE', 'Confirm the product in the store before enabling it for sale.', 409);
        }
        $before = $product->toArray();
        $updates = [];
        foreach (['activeForSale' => 'active_for_sale', 'storeStatus' => 'store_status', 'effectiveFrom' => 'effective_from', 'effectiveUntil' => 'effective_until'] as $input => $column) {
            if (array_key_exists($input, $data)) {
                $updates[$column] = $data[$input];
            }
        }
        $product->update($updates);
        $audit->record($request, 'billing.product.updated', $product, $before, $product->fresh()->toArray());

        return ApiResponse::success($product->fresh());
    }

    public function syncGoogleProduct(Request $request, StoreProduct $product, GooglePlayProvider $google, BillingAdminAuditService $audit)
    {
        abort_unless($product->platform === 'google', 422);
        $prices = $google->syncProduct($product);
        DB::transaction(function () use ($product, $prices): void {
            foreach ($prices as $price) {
                StorePriceSnapshot::query()->create([
                    'store_product_id' => $product->id, 'country_code' => $price['countryCode'],
                    'currency' => $price['currency'], 'customer_price' => $price['customerPrice'],
                    'formatted_price' => $price['formattedPrice'], 'billing_period' => $price['billingPeriod'],
                    'fetched_at' => now(),
                ]);
            }
            $product->update(['store_status' => 'active', 'last_synced_at' => now()]);
        });
        $audit->record($request, 'billing.product.synced', $product, null, ['priceCount' => count($prices)]);

        return ApiResponse::success(['product' => $product->fresh(), 'prices' => $prices]);
    }

    public function syncGoogleCatalog(Request $request, GooglePlayProvider $google, BillingAdminAuditService $audit)
    {
        $products = StoreProduct::query()->where('platform', 'google')->where('environment', config('billing.environment'))->get();
        $result = [];
        foreach ($products as $product) {
            $prices = $google->syncProduct($product);
            DB::transaction(function () use ($product, $prices): void {
                foreach ($prices as $price) {
                    StorePriceSnapshot::query()->create([
                        'store_product_id' => $product->id, 'country_code' => $price['countryCode'],
                        'currency' => $price['currency'], 'customer_price' => $price['customerPrice'],
                        'formatted_price' => $price['formattedPrice'], 'billing_period' => $price['billingPeriod'],
                        'fetched_at' => now(),
                    ]);
                }
                $product->update(['store_status' => 'active', 'last_synced_at' => now()]);
            });
            $result[] = ['productId' => $product->id, 'priceCount' => count($prices)];
        }
        $audit->record($request, 'billing.catalog.synced', 'google_catalog', null, ['products' => $result]);

        return ApiResponse::success(['products' => $result]);
    }

    public function transactions(Request $request)
    {
        $data = $request->validate(['userId' => ['nullable', 'ulid'], 'platform' => ['nullable', Rule::in(['apple', 'google'])], 'state' => ['nullable', 'string', 'max:48'], 'limit' => ['nullable', 'integer', 'between:1,100']]);
        $items = StorePurchase::query()->with(['user:id,email', 'storeProduct.plan'])
            ->when($data['userId'] ?? null, fn ($q, $value) => $q->where('user_id', $value))
            ->when($data['platform'] ?? null, fn ($q, $value) => $q->where('platform', $value))
            ->when($data['state'] ?? null, fn ($q, $value) => $q->where('state', $value))
            ->latest()->limit($data['limit'] ?? 50)->get()->makeHidden(['purchase_token', 'raw_reference']);

        return ApiResponse::success($items);
    }

    public function userBilling(User $user, EntitlementService $entitlements)
    {
        return ApiResponse::success([
            'user' => ['id' => (string) $user->id, 'email' => $user->email],
            'snapshot' => $entitlements->snapshot($user),
            'entitlements' => UserEntitlement::query()->where('user_id', $user->id)->with('plan')->latest()->get(),
            'manualGrants' => ManualEntitlementGrant::query()->where('user_id', $user->id)->with('plan')->latest()->get(),
        ]);
    }

    public function subscriptions(Request $request)
    {
        $limit = $request->validate(['limit' => ['nullable', 'integer', 'between:1,100']])['limit'] ?? 50;

        return ApiResponse::success(UserEntitlement::query()->where('source', 'store')->with(['plan', 'storePurchase', 'user:id,email'])->latest()->limit($limit)->get());
    }

    public function reconcileUser(Request $request, User $user, BillingAdminAuditService $audit)
    {
        ReconcileUserBilling::dispatch((string) $user->id)->afterCommit();
        $audit->record($request, 'billing.user.reconciliation_queued', $user, null, ['userId' => (string) $user->id]);

        return ApiResponse::success(['status' => 'queued'], 202);
    }

    public function grant(Request $request, User $user, BillingAdminAuditService $audit)
    {
        $data = $request->validate(['planCode' => ['required', 'string', 'exists:billing_plans,code'], 'startsAt' => ['nullable', 'date'], 'endsAt' => ['required', 'date', 'after:startsAt'], 'reason' => ['required', 'string', 'max:500']]);
        $plan = BillingPlan::query()->where('code', $data['planCode'])->firstOrFail();
        $grant = DB::transaction(function () use ($request, $user, $plan, $data): ManualEntitlementGrant {
            $grant = ManualEntitlementGrant::query()->create([
                'user_id' => $user->id, 'billing_plan_id' => $plan->id,
                'starts_at' => $data['startsAt'] ?? now(), 'ends_at' => $data['endsAt'],
                'reason' => $data['reason'], 'created_by_admin_id' => $request->user()->id,
            ]);
            UserEntitlement::query()->create([
                'user_id' => $user->id, 'billing_plan_id' => $plan->id,
                'entitlement_key' => 'manual:'.$grant->id, 'source' => 'manual', 'status' => 'active',
                'period_start' => $grant->starts_at, 'period_end' => $grant->ends_at,
                'auto_renew_enabled' => false, 'last_verified_at' => now(), 'verification_source' => 'admin',
            ]);

            return $grant;
        });
        $audit->record($request, 'billing.entitlement.granted', $grant, null, $grant->toArray());

        return ApiResponse::success($grant->load('plan'), 201);
    }

    public function revokeGrant(Request $request, ManualEntitlementGrant $grant, BillingAdminAuditService $audit)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $before = $grant->toArray();
        DB::transaction(function () use ($grant, $data): void {
            $grant->update(['revoked_at' => now(), 'reason' => $grant->reason.' | Revoke: '.$data['reason']]);
            UserEntitlement::query()->where('entitlement_key', 'manual:'.$grant->id)->update(['status' => 'revoked', 'revoked_at' => now()]);
        });
        $audit->record($request, 'billing.entitlement.revoked', $grant, $before, $grant->fresh()->toArray());

        return ApiResponse::success($grant->fresh());
    }

    public function revokeUserGrant(Request $request, User $user, ManualEntitlementGrant $grant, BillingAdminAuditService $audit)
    {
        abort_unless($grant->user_id === $user->id, 404);

        return $this->revokeGrant($request, $grant, $audit);
    }

    public function adjustCredits(Request $request, User $user, CreditLedgerService $credits, BillingAdminAuditService $audit)
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'between:-100,100', 'not_in:0'], 'reason' => ['required', 'string', 'max:500'], 'idempotencyKey' => ['required', 'string', 'max:120']]);
        $entry = $credits->adjust($user, $data['quantity'], 'admin:'.$request->user()->id.':'.$data['idempotencyKey'], $data['reason']);
        $audit->record($request, 'billing.credits.adjusted', $entry, null, $entry->toArray());

        return ApiResponse::success($entry, 201);
    }

    public function events(Request $request)
    {
        $data = $request->validate(['status' => ['nullable', 'string', 'max:32'], 'platform' => ['nullable', Rule::in(['apple', 'google'])], 'limit' => ['nullable', 'integer', 'between:1,100']]);
        $events = BillingEvent::query()->when($data['status'] ?? null, fn ($q, $v) => $q->where('processing_status', $v))->when($data['platform'] ?? null, fn ($q, $v) => $q->where('platform', $v))->latest()->limit($data['limit'] ?? 50)->get()->makeHidden('encrypted_payload_reference');

        return ApiResponse::success($events);
    }

    public function reprocessEvent(Request $request, BillingEvent $event, BillingAdminAuditService $audit)
    {
        $before = $event->toArray();
        $event->update(['processing_status' => 'received', 'processed_at' => null, 'error_message' => null]);
        ProcessBillingEvent::dispatch($event->id)->afterCommit();
        $audit->record($request, 'billing.event.reprocessed', $event, $before, $event->fresh()->toArray());

        return ApiResponse::success(['status' => 'queued'], 202);
    }

    public function auditLogs(Request $request)
    {
        $limit = $request->validate(['limit' => ['nullable', 'integer', 'between:1,100']])['limit'] ?? 50;

        return ApiResponse::success(BillingAdminAuditLog::query()->latest('created_at')->limit($limit)->get());
    }

    public function analytics()
    {
        return ApiResponse::success([
            'verifiedPurchases' => StorePurchase::query()->whereNotNull('last_verified_at')->count(),
            'activeStoreEntitlements' => UserEntitlement::query()->where('source', 'store')->whereIn('status', ['active', 'gracePeriod', 'canceledActiveUntilExpiry'])->where(fn ($query) => $query->whereNull('period_end')->orWhere('period_end', '>', now()))->count(),
            'manualEntitlementsActive' => ManualEntitlementGrant::query()->whereNull('revoked_at')->where('starts_at', '<=', now())->where('ends_at', '>', now())->count(),
            'eventsFailedOrPending' => BillingEvent::query()->whereNotIn('processing_status', ['processed', 'ignored'])->count(),
            'creditsOutstanding' => (int) CreditLedgerEntry::query()->whereIn('id', CreditLedgerEntry::query()->selectRaw('MAX(id)')->groupBy('user_id'))->sum('balance_after'),
            'financialNotice' => 'Operational counts only. Revenue requires official store financial reports including fees and taxes.',
        ]);
    }
}
