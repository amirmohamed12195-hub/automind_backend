<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessBillingEvent;
use App\Models\BillingEvent;
use App\Models\BillingPlan;
use App\Models\CreditLedgerEntry;
use App\Models\StoreProduct;
use App\Models\StorePurchase;
use App\Models\UserEntitlement;
use App\Services\Billing\BillingAdminAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminBillingDashboardController
{
    public function index(): View
    {
        if (! Schema::hasTable('billing_plans')) {
            return view('admin', [
                'billingOverview' => [
                    'activeSubscriptions' => 0,
                    'graceOrRetry' => 0,
                    'creditsOutstanding' => 0,
                    'eventsNeedingAttention' => 0,
                ],
                'billingPlans' => collect(),
                'billingProducts' => collect(),
                'billingTransactions' => collect(),
                'billingEvents' => collect(),
            ]);
        }

        $billingOverview = [
            'activeSubscriptions' => UserEntitlement::query()->whereIn('status', ['active', 'gracePeriod', 'canceledActiveUntilExpiry'])->where('period_end', '>', now())->count(),
            'graceOrRetry' => UserEntitlement::query()->whereIn('status', ['gracePeriod', 'billingRetry'])->count(),
            'creditsOutstanding' => (int) CreditLedgerEntry::query()->whereIn('id', CreditLedgerEntry::query()->selectRaw('MAX(id)')->groupBy('user_id'))->sum('balance_after'),
            'eventsNeedingAttention' => BillingEvent::query()->whereNotIn('processing_status', ['processed', 'ignored'])->count(),
        ];

        return view('admin', [
            'billingOverview' => $billingOverview,
            'billingPlans' => BillingPlan::query()->with(['localizations', 'storeProducts'])->orderBy('sort_order')->get(),
            'billingProducts' => StoreProduct::query()
                ->with(['plan', 'priceSnapshots' => fn ($query) => $query->latest('fetched_at')->limit(20)])
                ->orderBy('platform')->orderBy('environment')->orderBy('product_id')->get(),
            'billingTransactions' => StorePurchase::query()->with(['user:id,email', 'storeProduct.plan'])->latest()->limit(25)->get(),
            'billingEvents' => BillingEvent::query()->latest()->limit(25)->get(),
        ]);
    }

    public function updatePlan(Request $request, BillingPlan $plan, BillingAdminAuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'active' => ['required', 'boolean'], 'published' => ['required', 'boolean'],
            'recommended' => ['required', 'boolean'], 'maxVehicles' => ['nullable', 'integer', 'between:1,1000'],
            'reportsPerPeriod' => ['nullable', 'integer', 'between:1,1000'],
        ]);
        $before = $plan->toArray();
        $plan->update([
            'active' => $data['active'], 'published' => $data['published'], 'recommended' => $data['recommended'],
            'max_vehicles' => $data['maxVehicles'] ?? null, 'reports_per_period' => $data['reportsPerPeriod'] ?? null,
        ]);
        $audit->record($request, 'billing.plan.updated.web', $plan, $before, $plan->fresh()->toArray());

        return back()->with('billing_status', 'Plan updated.');
    }

    public function updateProduct(Request $request, StoreProduct $product, BillingAdminAuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'storeStatus' => ['required', Rule::in(['pending', 'active', 'rejected', 'retired'])],
            'activeForSale' => ['required', 'boolean'],
        ]);
        if ($data['activeForSale'] && $data['storeStatus'] !== 'active') {
            return back()->withErrors(['activeForSale' => 'A store product must be confirmed active before sale.']);
        }
        $before = $product->toArray();
        $product->update(['store_status' => $data['storeStatus'], 'active_for_sale' => $data['activeForSale']]);
        $audit->record($request, 'billing.product.updated.web', $product, $before, $product->fresh()->toArray());

        return back()->with('billing_status', 'Store product updated.');
    }

    public function reprocessEvent(Request $request, BillingEvent $event, BillingAdminAuditService $audit): RedirectResponse
    {
        $before = $event->toArray();
        $event->update(['processing_status' => 'received', 'processed_at' => null, 'error_message' => null]);
        ProcessBillingEvent::dispatch($event->id)->afterCommit();
        $audit->record($request, 'billing.event.reprocessed.web', $event, $before, $event->fresh()->toArray());

        return back()->with('billing_status', 'Billing event queued for reprocessing.');
    }
}
