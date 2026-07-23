<?php

namespace App\Services\Notifications;

use App\Contracts\FcmAccessTokenProvider;
use App\Contracts\PushNotificationProvider;
use App\DTO\PushNotificationResult;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use RuntimeException;

class FcmPushNotificationProvider implements PushNotificationProvider
{
    public function __construct(private readonly FcmAccessTokenProvider $accessTokens) {}

    public function send(array $deviceTokens, string $title, string $body, array $data = []): PushNotificationResult
    {
        $project = trim((string) config('services.fcm.project_id'));
        if ($project === '') {
            throw new RuntimeException('FCM_PROJECT_ID is not configured.');
        }

        $tokens = array_values(array_unique(array_filter($deviceTokens, fn ($token) => is_string($token) && trim($token) !== '')));
        if ($tokens === []) {
            return new PushNotificationResult(0, 0);
        }

        $accessToken = $this->accessTokens->accessToken();
        $sent = 0;
        $invalidTokens = [];
        $endpoint = 'https://fcm.googleapis.com/v1/projects/'.rawurlencode($project).'/messages:send';

        foreach ($tokens as $token) {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout((int) config('services.fcm.timeout_seconds', 15))
                ->post($endpoint, $this->payload($token, $title, $body, $data));

            if ($response->successful()) {
                $sent++;

                continue;
            }

            $errorCode = $this->fcmErrorCode($response);
            if (in_array($errorCode, ['UNREGISTERED', 'SENDER_ID_MISMATCH'], true)) {
                $invalidTokens[] = $token;
                Log::notice('Disabled an invalid FCM registration', [
                    'token_hash' => hash('sha256', $token),
                    'fcm_error' => $errorCode,
                ]);

                continue;
            }

            if (in_array($response->status(), [401, 403], true)) {
                $this->accessTokens->forget();
            }

            throw new RuntimeException(sprintf(
                'FCM delivery failed (HTTP %d, error %s).',
                $response->status(),
                $errorCode ?? 'UNKNOWN',
            ));
        }

        return new PushNotificationResult(count($tokens), $sent, $invalidTokens);
    }

    /** @param array<string, mixed> $data */
    private function payload(string $token, string $title, string $body, array $data): array
    {
        $message = [
            'token' => $token,
            'notification' => [
                'title' => mb_substr($title, 0, 160),
                'body' => mb_substr($body, 0, 1000),
            ],
            'data' => $this->stringData($data),
            'android' => [
                'priority' => 'high',
                'ttl' => '86400s',
                'notification' => [
                    'channel_id' => (string) config('services.fcm.android_channel_id', 'automind_high_importance'),
                    'sound' => 'default',
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                ],
            ],
            'apns' => [
                'headers' => [
                    'apns-priority' => '10',
                    'apns-push-type' => 'alert',
                ],
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                        'content-available' => 1,
                    ],
                ],
            ],
        ];

        return [
            'message' => $message,
            'validate_only' => (bool) config('services.fcm.validate_only', false),
        ];
    }

    /** @param array<string, mixed> $data */
    private function stringData(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $key = (string) $key;
            if ($key === '' || $key === 'from' || $key === 'message_type' || str_starts_with($key, 'google.') || str_starts_with($key, 'gcm.')) {
                continue;
            }
            if (is_bool($value)) {
                $result[$key] = $value ? 'true' : 'false';
            } elseif (is_scalar($value) || $value === null) {
                $result[$key] = (string) ($value ?? '');
            } else {
                try {
                    $result[$key] = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                } catch (JsonException) {
                    throw new RuntimeException("FCM data field {$key} is not JSON encodable.");
                }
            }
        }

        return $result;
    }

    private function fcmErrorCode(Response $response): ?string
    {
        $details = $response->json('error.details', []);
        if (is_array($details)) {
            foreach ($details as $detail) {
                if (is_array($detail) && isset($detail['errorCode']) && is_string($detail['errorCode'])) {
                    return $detail['errorCode'];
                }
            }
        }

        $status = $response->json('error.status');

        return is_string($status) ? $status : null;
    }
}
