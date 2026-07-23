<?php

namespace App\Console\Commands;

use App\Services\ProductionConfigurationValidator;
use Illuminate\Console\Command;

class CheckProductionConfiguration extends Command
{
    protected $signature = 'automind:check-production-config';

    protected $description = 'Validate required application and database production configuration without connecting to providers';

    public function handle(ProductionConfigurationValidator $validator): int
    {
        $errors = $validator->errors();

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $this->info('Application production configuration is valid; no external connection was made.');

        return self::SUCCESS;
    }
}
