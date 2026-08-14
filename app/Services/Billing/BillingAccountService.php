<?php

namespace App\Services\Billing;

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
                // Google accepts an opaque value of up to 64 characters. A
                // cryptographically random stored identifier is already
                // stable, non-reversible, and independent of key rotation.
                'google_obfuscated_account_id' => bin2hex(random_bytes(32)),
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
}
