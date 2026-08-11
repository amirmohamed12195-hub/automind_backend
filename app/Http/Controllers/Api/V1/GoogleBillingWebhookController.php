<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BillingException;
use App\Jobs\ProcessBillingEvent;
use App\Models\BillingEvent;
use App\Services\Billing\GooglePubSubAuthenticator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GoogleBillingWebhookController
{
    public function __invoke(Request $request, GooglePubSubAuthenticator $authenticator)
    {
        $authenticator->authenticate($request->header('Authorization'));
        $envelope = $request->validate([
            'message.messageId' => ['required', 'string', 'max:255'],
            'message.data' => ['required', 'string', 'max:100000'],
        ]);
        $decoded = base64_decode((string) data_get($envelope, 'message.data'), true);
        $payload = is_string($decoded) ? json_decode($decoded, true) : null;
        if (! is_array($payload) || ($payload['packageName'] ?? null) !== config('billing.google.package_name')) {
            throw new BillingException('INVALID_WEBHOOK_PAYLOAD', 'Google Play notification payload is invalid.', 400);
        }
        $eventType = isset($payload['subscriptionNotification']) ? 'SUBSCRIPTION_NOTIFICATION'
            : (isset($payload['oneTimeProductNotification']) ? 'ONE_TIME_PRODUCT_NOTIFICATION'
                : (isset($payload['voidedPurchaseNotification']) ? 'VOIDED_PURCHASE_NOTIFICATION' : 'UNKNOWN'));
        $event = BillingEvent::query()->firstOrCreate(
            ['platform' => 'google', 'external_event_id' => (string) data_get($envelope, 'message.messageId')],
            [
                'event_type' => $eventType,
                'event_subtype' => (string) (data_get($payload, 'subscriptionNotification.notificationType') ?? data_get($payload, 'oneTimeProductNotification.notificationType') ?? ''),
                'environment' => config('billing.environment'),
                'processing_status' => 'received',
                'received_at' => now(),
                'encrypted_payload_reference' => $payload,
            ],
        );
        if ($event->wasRecentlyCreated) {
            ProcessBillingEvent::dispatch($event->id)->afterCommit();
            Log::info('billing_webhook_received', ['platform' => 'google', 'event_id' => $event->id, 'event_type' => $event->event_type]);
        }

        return response()->noContent(202);
    }
}
