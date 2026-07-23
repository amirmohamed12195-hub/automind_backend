<?php

namespace App\Contracts;

interface FcmAccessTokenProvider
{
    public function accessToken(): string;

    public function forget(): void;
}
