<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BillingException;
use App\Jobs\ProcessBillingEvent;
use App\Models\BillingEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AppleBillingWebhookController
{
    public function __invoke(Request $request)
    {
        $data = $request->validate(['signedPayload' => ['required', 'string', 'max:100000']]);
        $payload = $this->unverifiedPayload($data['signedPayload']);
        $eventId = trim((string) ($payload['notificationUUID'] ?? ''));
        if ($eventId === '') {
            throw new BillingException('INVALID_WEBHOOK_PAYLOAD', 'Apple notification UUID is missing.', 400);
        }
        $environment = strtolower((string) data_get($payload, 'data.environment')) === 'production' ? 'production' : 'sandbox';
        $event = BillingEvent::query()->firstOrCreate(
            ['platform' => 'apple', 'external_event_id' => $eventId],
            [
                'event_type' => (string) ($payload['notificationType'] ?? 'UNKNOWN'),
                'event_subtype' => isset($payload['subtype']) ? (string) $payload['subtype'] : null,
                'environment' => $environment,
                'processing_status' => 'received',
                'received_at' => now(),
                'encrypted_payload_reference' => ['signedPayload' => $data['signedPayload']],
            ],
        );
        if ($event->wasRecentlyCreated) {
            ProcessBillingEvent::dispatch($event->id)->afterCommit();
            Log::info('billing_webhook_received', ['platform' => 'apple', 'event_id' => $event->id, 'event_type' => $event->event_type]);
        }

        return response()->noContent(202);
    }

    /** @return array<string, mixed> */
    private function unverifiedPayload(string $signedPayload): array
    {
        $parts = explode('.', $signedPayload);
        if (count($parts) !== 3) {
            throw new BillingException('INVALID_WEBHOOK_PAYLOAD', 'Apple notification envelope is malformed.', 400);
        }
        $encoded = strtr($parts[1], '-_', '+/').str_repeat('=', (4 - strlen($parts[1]) % 4) % 4);
        $decoded = base64_decode($encoded, true);
        $payload = is_string($decoded) ? json_decode($decoded, true) : null;
        if (! is_array($payload)) {
            throw new BillingException('INVALID_WEBHOOK_PAYLOAD', 'Apple notification envelope is invalid.', 400);
        }

        return $payload;
    }
}
