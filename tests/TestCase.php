<?php

namespace Tests;

use App\Contracts\PhoneVerificationProvider;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Fakes\FakePhoneVerificationProvider;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(PhoneVerificationProvider::class, new FakePhoneVerificationProvider);
    }
}
