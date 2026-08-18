<?php

namespace Tests\Fakes;

use App\Contracts\PhoneVerificationProvider;

class FakePhoneVerificationProvider implements PhoneVerificationProvider
{
    /** @var list<string> */
    public array $started = [];

    public string $validCode = '123456';

    public function start(string $phone): void
    {
        $this->started[] = $phone;
    }

    public function check(string $phone, string $code): bool
    {
        return $code === $this->validCode;
    }
}
