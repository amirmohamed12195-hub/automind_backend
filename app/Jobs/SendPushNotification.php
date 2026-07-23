<?php

namespace App\Jobs;

use App\Contracts\PushNotificationProvider;
use App\Models\DeviceToken;
use App\Models\UserNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 45;

    public bool $failOnTimeout = true;

    public function __construct(public readonly string $userId, public readonly string $title, public readonly string $body, public readonly array $data = [])
    {
        $this->onQueue('notifications');
    }

    public function backoff(): array
    {
        return [random_int(25, 35), random_int(105, 135)];
    }

    public function handle(PushNotificationProvider $provider): void
    {
        $staleBefore = now()->subDays(max(1, (int) config('services.fcm.stale_token_days', 90)));
        DeviceToken::query()
            ->where('user_id', $this->userId)
            ->where('enabled', true)
            ->where('last_seen_at', '<', $staleBefore)
            ->update(['enabled' => false, 'updated_at' => now()]);

        $devices = DeviceToken::query()
            ->where('user_id', $this->userId)
            ->where('enabled', true)
            ->where(fn ($query) => $query->whereNull('last_seen_at')->orWhere('last_seen_at', '>=', $staleBefore))
            ->get();
        if ($devices->isEmpty()) {
            return;
        }

        $result = $provider->send(
            $devices->map(fn (DeviceToken $device): string => $device->push_token)->all(),
            $this->title,
            $this->body,
            $this->data,
        );

        if ($result->invalidTokens !== []) {
            DeviceToken::query()
                ->whereIn('token_hash', array_map(fn (string $token): string => hash('sha256', $token), $result->invalidTokens))
                ->update(['enabled' => false, 'updated_at' => now()]);
        }

        $notificationId = $this->data['notificationId'] ?? null;
        if ($result->sent > 0 && is_string($notificationId) && $notificationId !== '') {
            UserNotification::query()
                ->whereKey($notificationId)
                ->where('user_id', $this->userId)
                ->whereNull('sent_at')
                ->update(['sent_at' => now(), 'updated_at' => now()]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Push notification delivery exhausted its retries', [
            'user_id' => $this->userId,
            'notification_id' => $this->data['notificationId'] ?? null,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
