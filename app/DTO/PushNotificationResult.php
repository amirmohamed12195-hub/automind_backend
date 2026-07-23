<?php

namespace App\DTO;

final readonly class PushNotificationResult
{
    /**
     * @param  list<string>  $invalidTokens
     */
    public function __construct(
        public int $attempted,
        public int $sent,
        public array $invalidTokens = [],
    ) {}
}
