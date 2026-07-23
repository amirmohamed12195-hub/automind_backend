<?php

namespace Tests\Unit;

use App\Contracts\FcmAccessTokenProvider;
use App\Services\Notifications\FcmPushNotificationProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class FcmPushNotificationProviderTest extends TestCase
{
    public function test_it_sends_cross_platform_payloads_and_reports_invalid_tokens(): void
    {
        config()->set('services.fcm.project_id', 'automind-d7a2b');
        config()->set('services.fcm.android_channel_id', 'automind_high_importance');
        config()->set('services.fcm.validate_only', false);
        $tokens = new FakeFcmAccessTokenProvider;
        Http::fake(function (Request $request) {
            $token = $request->data()['message']['token'] ?? null;

            return $token === 'invalid-token'
                ? Http::response([
                    'error' => [
                        'status' => 'NOT_FOUND',
                        'details' => [['errorCode' => 'UNREGISTERED']],
                    ],
                ], 404)
                : Http::response(['name' => 'projects/automind-d7a2b/messages/1'], 200);
        });

        $result = (new FcmPushNotificationProvider($tokens))->send(
            ['valid-token', 'invalid-token'],
            'Report ready',
            'Open AutoMind',
            ['reportId' => 'report-1', 'context' => ['severity' => 'high']],
        );

        $this->assertSame(2, $result->attempted);
        $this->assertSame(1, $result->sent);
        $this->assertSame(['invalid-token'], $result->invalidTokens);
        $this->assertSame(0, $tokens->forgetCalls);
        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return str_contains($request->url(), '/v1/projects/automind-d7a2b/messages:send')
                && $request->hasHeader('Authorization', 'Bearer test-access-token')
                && ($payload['message']['android']['notification']['channel_id'] ?? null) === 'automind_high_importance'
                && ($payload['message']['apns']['headers']['apns-push-type'] ?? null) === 'alert'
                && ($payload['message']['data']['context'] ?? null) === '{"severity":"high"}';
        });
    }

    public function test_it_discards_cached_oauth_token_after_unauthorized_response(): void
    {
        config()->set('services.fcm.project_id', 'automind-d7a2b');
        $tokens = new FakeFcmAccessTokenProvider;
        Http::fake(fn () => Http::response(['error' => ['status' => 'UNAUTHENTICATED']], 401));

        try {
            (new FcmPushNotificationProvider($tokens))->send(['token'], 'Title', 'Body');
            $this->fail('Expected FCM delivery to fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('HTTP 401', $exception->getMessage());
        }

        $this->assertSame(1, $tokens->forgetCalls);
    }
}

class FakeFcmAccessTokenProvider implements FcmAccessTokenProvider
{
    public int $forgetCalls = 0;

    public function accessToken(): string
    {
        return 'test-access-token';
    }

    public function forget(): void
    {
        $this->forgetCalls++;
    }
}
