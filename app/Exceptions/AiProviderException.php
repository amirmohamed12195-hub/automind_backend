<?php

namespace App\Exceptions;

use RuntimeException;

class AiProviderException extends RuntimeException
{
    public function __construct(string $message, public readonly string $category, public readonly bool $transient = false, public readonly ?int $retryAfterSeconds = null)
    {
        parent::__construct($message);
    }
}
