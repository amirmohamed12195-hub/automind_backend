<?php

namespace Tests\Feature;

use App\Jobs\ProcessOpenAiWebhook;
use App\Services\Media\MediaToolchain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class SystemWebhookSecurityTest extends ApiTestCase
{
    public function test_health_version_and_request_id_do_not_call_paid_provider(): void
    {
        $this->getJson('/api/v1/health?type=liveness')->assertOk()->assertJsonPath('data.status', 'alive')->assertHeader('X-Request-Id');
        $this->getJson('/api/v1/health?type=unknown')->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $readiness = $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('data.checks.database', 'ok');
        $this->assertContains($readiness->json('data.checks.mediaTools'), ['ok', 'optional']);
        $this->getJson('/api/v1/version')->assertOk()->assertJsonStructure(['data' => ['apiVersion', 'laravelVersion', 'environment']]);
    }

    public function test_openai_webhook_rejects_bad_signature_and_deduplicates_replay(): void
    {
        Queue::fake();
        $secretBytes = random_bytes(32);
        config(['openai.webhook_secret' => 'whsec_'.base64_encode($secretBytes)]);
        $id = 'wh_test_1';
        $timestamp = (string) time();
        $payload = json_encode(['id' => 'evt_1', 'type' => 'response.completed', 'data' => ['id' => 'resp_1']]);
        $this->call('POST', '/api/v1/webhooks/openai', [], [], [], ['HTTP_WEBHOOK_ID' => $id, 'HTTP_WEBHOOK_TIMESTAMP' => $timestamp, 'HTTP_WEBHOOK_SIGNATURE' => 'v1,bad', 'CONTENT_TYPE' => 'application/json'], $payload)->assertBadRequest()->assertJsonPath('error.code', 'INVALID_WEBHOOK_SIGNATURE');
        $signature = base64_encode(hash_hmac('sha256', "$id.$timestamp.$payload", $secretBytes, true));
        $server = ['HTTP_WEBHOOK_ID' => $id, 'HTTP_WEBHOOK_TIMESTAMP' => $timestamp, 'HTTP_WEBHOOK_SIGNATURE' => 'v1,'.$signature, 'CONTENT_TYPE' => 'application/json'];
        $this->call('POST', '/api/v1/webhooks/openai', [], [], [], $server, $payload)->assertStatus(202);
        $this->call('POST', '/api/v1/webhooks/openai', [], [], [], $server, $payload)->assertStatus(202);
        $this->assertDatabaseCount('webhook_receipts', 1);
        Queue::assertPushed(ProcessOpenAiWebhook::class, 1);
    }

    public function test_readiness_remains_ready_when_optional_legacy_media_tools_are_unavailable(): void
    {
        $this->app->instance(MediaToolchain::class, new class extends MediaToolchain
        {
            public function assertAvailable(): void
            {
                throw new \RuntimeException('Media tools are intentionally unavailable in this test.');
            }
        });

        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.checks.mediaTools', 'optional');
    }

    public function test_readiness_fails_when_a_database_queue_job_is_stalled(): void
    {
        config([
            'queue.default' => 'database',
            'queue.connections.database.connection' => DB::getDefaultConnection(),
            'automind.queue.critical' => ['diagnostic-ai'],
            'automind.queue.stale_after_seconds' => 30,
        ]);
        DB::table('jobs')->insert([
            'queue' => 'diagnostic-ai',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => time() - 31,
            'created_at' => time() - 31,
        ]);

        $this->getJson('/api/v1/health')
            ->assertServiceUnavailable()
            ->assertJsonPath('data.status', 'not_ready')
            ->assertJsonPath('data.checks.queue', 'failed')
            ->assertJsonPath('data.queue.connection', 'database')
            ->assertJsonPath('data.queue.depth', 1);
    }
}
