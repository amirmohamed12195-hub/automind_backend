<?php

namespace App\Services\Billing;

use App\Models\BillingPlan;
use App\Models\StoreProduct;
use App\Models\User;

class BillingCatalogService
{
    /** @return array<string, mixed> */
    public function forUser(User $user, string $platform): array
    {
        $locale = in_array(app()->getLocale(), ['en', 'ar'], true) ? app()->getLocale() : 'en';
        $country = strtoupper((string) ($user->country_code ?: 'EG'));
        $environment = strtolower((string) config('billing.environment')) === 'production' ? 'production' : 'sandbox';
        $plans = BillingPlan::query()
            ->where('active', true)
            ->where('published', true)
            ->whereHas('regions', fn ($query) => $query->where('country_code', $country)->where('visible', true)
                ->where(fn ($window) => $window->whereNull('available_from')->orWhere('available_from', '<=', now()))
                ->where(fn ($window) => $window->whereNull('available_until')->orWhere('available_until', '>', now())))
            ->with([
                'localizations' => fn ($query) => $query->whereIn('locale', [$locale, 'en']),
                'features',
                'storeProducts' => fn ($query) => $query->where('platform', $platform)->where('environment', $environment)
                    ->where(fn ($window) => $window->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
                    ->where(fn ($window) => $window->whereNull('effective_until')->orWhere('effective_until', '>', now()))
                    ->with(['priceSnapshots' => fn ($prices) => $prices->where(fn ($q) => $q->where('country_code', $country)->orWhereNull('country_code'))->latest('fetched_at')]),
            ])
            ->orderBy('sort_order')
            ->get()
            ->map(function (BillingPlan $plan) use ($locale): array {
                $copy = $plan->localizations->firstWhere('locale', $locale) ?? $plan->localizations->firstWhere('locale', 'en');
                $products = $plan->storeProducts->map(function (StoreProduct $product): array {
                    $price = $product->priceSnapshots->first();

                    return [
                        'productId' => $product->product_id,
                        'productType' => $product->product_type,
                        'basePlanId' => $product->base_plan_id,
                        'offerId' => $product->offer_id,
                        'availableForSale' => (bool) $product->active_for_sale && $product->store_status === 'active',
                        'storeStatus' => $product->store_status,
                        'referencePrice' => $price ? [
                            'formatted' => $price->formatted_price,
                            'currency' => $price->currency,
                            'amount' => (string) $price->customer_price,
                            'billingPeriod' => $price->billing_period,
                            'fetchedAt' => $price->fetched_at?->toIso8601String(),
                        ] : null,
                    ];
                })->values()->all();

                return [
                    'code' => $plan->code,
                    'type' => $plan->type,
                    'name' => $copy->display_name ?? $plan->code,
                    'shortDescription' => $copy?->short_description,
                    'fullDescription' => $copy?->full_description,
                    'featureCopy' => $copy->feature_copy_json ?? [],
                    'recommended' => (bool) $plan->recommended,
                    'badge' => $copy?->badge_text,
                    'maxVehicles' => $plan->max_vehicles,
                    'reportsPerPeriod' => $plan->reports_per_period,
                    'features' => $plan->features->mapWithKeys(fn ($feature) => [
                        $feature->feature_key => $feature->limit_value ?? (bool) $feature->enabled,
                    ])->all(),
                    'products' => $products,
                ];
            })->values()->all();

        return [
            'enabled' => (bool) config('billing.enabled'),
            'environment' => $environment,
            'countryCode' => $country,
            'locale' => $locale,
            'platform' => $platform,
            'plans' => $plans,
            'termsUrl' => config('billing.terms_url'),
            'privacyUrl' => config('billing.privacy_url'),
        ];
    }
}
