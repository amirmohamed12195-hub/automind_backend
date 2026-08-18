<?php

namespace App\Exceptions;

use RuntimeException;

class PhoneVerificationException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }
}
