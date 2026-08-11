<?php

namespace App\Exceptions;

use RuntimeException;

class BillingException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus = 422,
        public readonly bool $retryable = false,
    ) {
        parent::__construct($message);
    }
}
