<?php

namespace App\Contracts;

use App\DTO\PushNotificationResult;

interface PushNotificationProvider
{
    /**
     * @param  list<string>  $deviceTokens
     * @param  array<string, mixed>  $data
     */
    public function send(array $deviceTokens, string $title, string $body, array $data = []): PushNotificationResult;
}
