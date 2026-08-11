<?php

namespace App\Services\Billing;

use App\Exceptions\BillingException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GooglePubSubAuthenticator
{
    /** @return array<string, mixed> */
    public function authenticate(?string $authorization): array
    {
        $audience = trim((string) config('billing.google.pubsub_audience'));
        if ($audience === '' || ! is_string($authorization) || ! str_starts_with($authorization, 'Bearer ')) {
            throw new BillingException('WEBHOOK_AUTHENTICATION_FAILED', 'Google Pub/Sub authentication is not configured or missing.', 401);
        }
        $token = trim(substr($authorization, 7));
        try {
            $keys = Cache::remember('billing:google-oidc-jwks', now()->addMinutes(30), function (): array {
                $response = Http::timeout(10)->get('https://www.googleapis.com/oauth2/v3/certs');
                if (! $response->successful() || ! is_array($response->json())) {
                    throw new \RuntimeException('Google OIDC keys are unavailable.');
                }

                return $response->json();
            });
            $claims = (array) JWT::decode($token, JWK::parseKeySet($keys));
        } catch (\Throwable $e) {
            throw new BillingException('WEBHOOK_AUTHENTICATION_FAILED', 'Google Pub/Sub token validation failed.', 401, true);
        }
        $audiences = is_array($claims['aud'] ?? null) ? $claims['aud'] : [$claims['aud'] ?? null];
        $issuer = (string) ($claims['iss'] ?? '');
        if (! in_array($audience, $audiences, true) || ! in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true)) {
            throw new BillingException('WEBHOOK_AUTHENTICATION_FAILED', 'Google Pub/Sub token claims are invalid.', 401);
        }
        $expectedEmail = trim((string) config('billing.google.pubsub_service_account_email'));
        if ($expectedEmail !== '' && ! hash_equals(strtolower($expectedEmail), strtolower((string) ($claims['email'] ?? '')))) {
            throw new BillingException('WEBHOOK_AUTHENTICATION_FAILED', 'Google Pub/Sub service account does not match.', 401);
        }
        if (isset($claims['email_verified']) && ! filter_var($claims['email_verified'], FILTER_VALIDATE_BOOL)) {
            throw new BillingException('WEBHOOK_AUTHENTICATION_FAILED', 'Google Pub/Sub email is not verified.', 401);
        }

        return $claims;
    }
}
