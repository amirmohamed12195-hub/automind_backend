<?php

namespace App\DTO;

final readonly class AiProviderResult
{
    public function __construct(
        public array $data,
        public ?string $responseId,
        public string $model,
        public string $endpoint,
        public array $usage = [],
        public array $metadata = [],
    ) {}
}
