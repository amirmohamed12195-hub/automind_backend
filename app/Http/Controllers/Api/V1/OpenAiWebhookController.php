<?php

namespace App\Http\Controllers\Api\V1;

use App\Jobs\ProcessOpenAiWebhook;
use App\Models\WebhookReceipt;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class OpenAiWebhookController
{
    public function __invoke(Request $request)
    {
        $secret = (string) config('openai.webhook_secret');
        $id = (string) $request->header('webhook-id');
        $timestamp = (string) $request->header('webhook-timestamp');
        $signature = (string) $request->header('webhook-signature');
        $raw = $request->getContent();
        if ($secret === '' || $id === '' || ! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300 || ! $this->valid($secret, $id, $timestamp, $raw, $signature)) {
            return ApiResponse::error('INVALID_WEBHOOK_SIGNATURE', __('api.webhook_invalid'), 400);
        }
        $event = json_decode($raw, true);
        if (! is_array($event) || ! isset($event['id'], $event['type'])) {
            return ApiResponse::error('INVALID_WEBHOOK_PAYLOAD', __('api.validation_failed'), 400);
        }
        $receipt = WebhookReceipt::query()->firstOrCreate(['provider' => 'openai', 'provider_event_id' => $id], ['event_type' => $event['type'], 'provider_object_id' => data_get($event, 'data.id'), 'payload_hash' => hash('sha256', $raw), 'received_at' => now(), 'status' => 'received']);
        if ($receipt->wasRecentlyCreated) {
            ProcessOpenAiWebhook::dispatch($receipt->id)->afterCommit();
        }

        return response()->noContent(202);
    }

    private function valid(string $secret, string $id, string $timestamp, string $raw, string $header): bool
    {
        $encoded = str_starts_with($secret, 'whsec_') ? substr($secret, 6) : $secret;
        $key = base64_decode($encoded, true);
        if ($key === false) {
            $key = $secret;
        }
        $expected = base64_encode(hash_hmac('sha256', "$id.$timestamp.$raw", $key, true));
        foreach (preg_split('/[\s,]+/', $header) ?: [] as $part) {
            $candidate = str_starts_with($part, 'v1,') ? substr($part, 3) : (str_starts_with($part, 'v1=') ? substr($part, 3) : $part);
            if ($candidate !== '' && hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }
}
