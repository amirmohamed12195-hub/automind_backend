<?php

namespace App\Console\Commands;

use App\Services\Billing\BillingConfigurationValidator;
use Illuminate\Console\Command;

class CheckBillingConfiguration extends Command
{
    protected $signature = 'automind:check-billing-config
                            {--require-enabled : Validate all store credentials even while BILLING_ENABLED is false}';

    protected $description = 'Validate Apple, Google Play, webhook, and billing production configuration without provider calls';

    public function handle(BillingConfigurationValidator $validator): int
    {
        $errors = $validator->errors((bool) $this->option('require-enabled'));
        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $this->info(config('billing.enabled') || $this->option('require-enabled')
            ? 'Billing configuration is structurally valid; no store request was made.'
            : 'Billing is disabled; store credentials were not checked for this deployment.');

        return self::SUCCESS;
    }
}
