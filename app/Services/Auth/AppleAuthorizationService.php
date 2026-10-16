<?php

namespace App\Services\Auth;

use App\Models\AppleAuthorization;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class AppleAuthorizationService
{
    public function __construct(private readonly SocialIdentityVerifier $verifier) {}

    /** Bind the exchanged grant to the verified identity, never to client-supplied user IDs. */
    public function exchange(string $code, string $nonce, array $identity): array
    {
        $clientId = (string) ($identity['audience'] ?? '');
        $data = [
            'client_id' => $clientId,
            'client_secret' => $this->clientSecret($clientId),
            'code' => $code,
            'grant_type' => 'authorization_code',
        ];
        if ($clientId === config('services.apple.service_id')) {
            $data['redirect_uri'] = config('services.apple.redirect_uri');
        }
        // Do not use throw(): provider response bodies and request tokens must not reach logs.
        $response = Http::asForm()->acceptJson()->connectTimeout(5)->timeout(15)
            ->post('https://appleid.apple.com/auth/token', $data);
        if (! $response->successful() || ! is_string($response->json('refresh_token')) || ! is_string($response->json('id_token'))) {
            throw new RuntimeException('Apple authorization exchange failed.');
        }
        $verified = $this->verifier->verify('apple', $response->json('id_token'), $nonce);
        if ($verified['subject'] !== $identity['subject'] || ($verified['audience'] ?? null) !== $clientId) {
            throw new RuntimeException('Apple authorization identity mismatch.');
        }

        return ['client_id' => $clientId, 'refresh_token' => $response->json('refresh_token')];
    }

    public function revokePending(?array $grantIds = null): void
    {
        AppleAuthorization::query()->when($grantIds !== null, fn ($query) => $query->whereIn('id', $grantIds))->where(function ($query): void {
            $query->whereNotNull('revocation_requested_at')->orWhereNull('user_id');
        })->where(function ($query): void {
            $query->whereNull('retry_at')->orWhere('retry_at', '<=', now());
        })->eachById(function (AppleAuthorization $grant): void {
            try {
                $response = Http::asForm()->connectTimeout(5)->timeout(15)->post('https://appleid.apple.com/auth/revoke', [
                    'client_id' => $grant->client_id,
                    'client_secret' => $this->clientSecret($grant->client_id),
                    'token' => $grant->refresh_token,
                    'token_type_hint' => 'refresh_token',
                ]);
                if ($response->status() !== 200) {
                    throw new RuntimeException('Apple revocation failed.');
                }
                $grant->delete();
            } catch (Throwable) {
                $grant->update(['attempts' => $grant->attempts + 1, 'retry_at' => now()->addMinutes(min(1440, 15 * (2 ** min(7, $grant->attempts))))]);
                Log::warning('Apple authorization revocation pending retry.', ['grantId' => $grant->id]);
            }
        }, 50);
    }

    public function clientSecret(string $clientId): string
    {
        if (! in_array($clientId, config('services.apple.client_ids', []), true)) {
            throw new RuntimeException('Apple client is not configured.');
        }
        $teamId = config('services.apple.team_id');
        $keyId = config('services.apple.sign_in_key_id');
        $path = config('services.apple.sign_in_private_key_path');
        $key = is_string($path) && is_readable($path) ? file_get_contents($path) : false;
        if (! $teamId || ! $keyId || ! $key) {
            throw new RuntimeException('Apple Sign in server credentials are not configured.');
        }

        return JWT::encode([
            'iss' => $teamId, 'iat' => now()->timestamp, 'exp' => now()->addMinutes(5)->timestamp,
            'aud' => 'https://appleid.apple.com', 'sub' => $clientId,
        ], $key, 'ES256', $keyId);
    }
}
