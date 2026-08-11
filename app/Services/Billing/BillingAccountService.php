<?php

namespace App\Services\Billing;

use App\Exceptions\BillingException;
use App\Models\BillingAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingAccountService
{
    public function forUser(User $user): BillingAccount
    {
        return DB::transaction(function () use ($user): BillingAccount {
            $existing = BillingAccount::query()->where('user_id', $user->id)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            return BillingAccount::query()->create([
                'user_id' => $user->id,
                'apple_app_account_token' => (string) Str::uuid(),
                'google_obfuscated_account_id' => hash_hmac('sha256', 'automind-billing-user:'.$user->id, $this->secret()),
            ]);
        }, 3);
    }

    public function findByStoreIdentifier(?string $identifier): ?BillingAccount
    {
        if (! is_string($identifier) || trim($identifier) === '') {
            return null;
        }

        return BillingAccount::query()
            ->where('apple_app_account_token', trim($identifier))
            ->orWhere('google_obfuscated_account_id', trim($identifier))
            ->first();
    }

    private function secret(): string
    {
        $secret = trim((string) config('billing.account_obfuscation_secret'));
        if ($secret === '' && ! app()->environment('production')) {
            $secret = (string) config('app.key');
        }
        if ($secret === '') {
            throw new BillingException('BILLING_CONFIGURATION_INVALID', 'The billing account secret is not configured.', 503);
        }

        return $secret;
    }
}
