<?php

namespace App\Http\Controllers\Api\V1;

use App\Jobs\ReconcileUserBilling;
use App\Models\CreditLedgerEntry;
use App\Models\StorePurchase;
use App\Services\Billing\BillingAccountService;
use App\Services\Billing\BillingCatalogService;
use App\Services\Billing\CreditLedgerService;
use App\Services\Billing\EntitlementService;
use App\Services\Billing\PurchaseVerificationService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BillingController
{
    public function catalog(Request $request, BillingCatalogService $catalog)
    {
        $data = $request->validate(['platform' => ['required', Rule::in(['apple', 'google'])]]);

        return ApiResponse::success($catalog->forUser($request->user(), $data['platform']));
    }

    public function account(Request $request, BillingAccountService $accounts)
    {
        $account = $accounts->forUser($request->user());

        return ApiResponse::success([
            'appleAppAccountToken' => $account->apple_app_account_token,
            'googleObfuscatedAccountId' => $account->google_obfuscated_account_id,
        ]);
    }

    public function entitlements(Request $request, EntitlementService $entitlements)
    {
        return ApiResponse::success($entitlements->snapshot($request->user()));
    }

    public function credits(Request $request, CreditLedgerService $credits)
    {
        $entries = CreditLedgerEntry::query()->where('user_id', $request->user()->id)->latest('id')->limit(50)->get()->map(fn ($entry) => [
            'id' => (string) $entry->id,
            'type' => $entry->entry_type,
            'quantity' => $entry->quantity,
            'balanceAfter' => $entry->balance_after,
            'reason' => $entry->reason,
            'createdAt' => $entry->created_at?->toIso8601String(),
        ])->all();

        return ApiResponse::success(['balance' => $credits->balance($request->user()), 'entries' => $entries]);
    }

    public function verifyApple(Request $request, PurchaseVerificationService $purchases)
    {
        $data = $request->validate([
            'transactionId' => ['required_without:signedTransactionInfo', 'nullable', 'string', 'max:255'],
            'signedTransactionInfo' => ['required_without:transactionId', 'nullable', 'string', 'max:20000'],
            'environment' => ['sometimes', Rule::in(['sandbox', 'production'])],
        ]);

        return ApiResponse::success($this->purchasePayload($purchases->verifyForUser($request->user(), 'apple', $data)), 200);
    }

    public function verifyGoogle(Request $request, PurchaseVerificationService $purchases)
    {
        $data = $request->validate([
            'purchaseToken' => ['required', 'string', 'max:4096'],
            'productId' => ['required', 'string', 'max:255'],
            'basePlanId' => ['sometimes', 'nullable', 'string', 'max:255'],
            'environment' => ['sometimes', Rule::in(['sandbox', 'production'])],
        ]);

        return ApiResponse::success($this->purchasePayload($purchases->verifyForUser($request->user(), 'google', $data)), 200);
    }

    public function restore(Request $request, PurchaseVerificationService $purchases)
    {
        $data = $request->validate([
            'purchases' => ['required', 'array', 'max:100'],
            'purchases.*.platform' => ['required', Rule::in(['apple', 'google'])],
            'purchases.*.transactionId' => ['nullable', 'string', 'max:255'],
            'purchases.*.signedTransactionInfo' => ['nullable', 'string', 'max:20000'],
            'purchases.*.purchaseToken' => ['nullable', 'string', 'max:4096'],
            'purchases.*.productId' => ['nullable', 'string', 'max:255'],
            'purchases.*.basePlanId' => ['nullable', 'string', 'max:255'],
            'purchases.*.environment' => ['sometimes', Rule::in(['sandbox', 'production'])],
        ]);
        $restored = [];
        foreach ($data['purchases'] as $proof) {
            $platform = $proof['platform'];
            unset($proof['platform']);
            $restored[] = $this->purchasePayload($purchases->verifyForUser($request->user(), $platform, $proof, 'restore'));
        }

        return ApiResponse::success(['purchases' => $restored]);
    }

    public function subscription(Request $request, EntitlementService $entitlements)
    {
        return ApiResponse::success($entitlements->snapshot($request->user())['access']);
    }

    public function manage(Request $request)
    {
        $platform = $request->validate(['platform' => ['required', Rule::in(['apple', 'google'])]])['platform'];

        return ApiResponse::success(['url' => config("billing.$platform.manage_url")]);
    }

    public function reconcile(Request $request)
    {
        ReconcileUserBilling::dispatch($request->user()->id)->afterCommit();

        return ApiResponse::success(['status' => 'queued'], 202);
    }

    /** @return array<string, mixed> */
    private function purchasePayload(StorePurchase $purchase): array
    {
        return [
            'id' => (string) $purchase->id,
            'platform' => $purchase->platform,
            'productId' => $purchase->product_id,
            'basePlanId' => $purchase->base_plan_id,
            'state' => $purchase->state,
            'acknowledged' => (bool) $purchase->acknowledged,
            'consumed' => (bool) $purchase->consumed,
            'expiresAt' => $purchase->expires_at?->toIso8601String(),
            'planCode' => $purchase->storeProduct?->plan?->code,
        ];
    }
}
