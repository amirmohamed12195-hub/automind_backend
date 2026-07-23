<?php

namespace App\Console\Commands;

use App\Services\Ai\OpenAiConfigurationValidator;
use Illuminate\Console\Command;

class CheckOpenAiConfiguration extends Command
{
    protected $signature = 'automind:check-provider-config {--allow-missing-key : Validate model capabilities without requiring production secrets or pricing}';

    protected $description = 'Validate OpenAI model capability, endpoint, webhook, and pricing configuration without making a paid call';

    public function handle(OpenAiConfigurationValidator $validator): int
    {
        $errors = $validator->errors(! $this->option('allow-missing-key'));
        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        $this->info('OpenAI API configuration is valid; no provider request was made.');

        return self::SUCCESS;
    }
}
