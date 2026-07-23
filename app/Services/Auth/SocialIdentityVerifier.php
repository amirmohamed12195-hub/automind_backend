<?php

namespace App\Services\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SocialIdentityVerifier
{
    public function verify(string $provider, string $token, ?string $nonce = null): array
    {
        if (! in_array($provider, ['google', 'apple'], true)) {
            throw new RuntimeException('Unsupported social provider.');
        }
        $config = config("services.$provider");
        $clientIds = $config['client_ids'] ?? [];
        if ($clientIds === []) {
            throw new RuntimeException('Social identity provider is not configured.');
        }
        $jwks = Cache::remember("social-jwks:$provider", now()->addHours(6), fn () => Http::acceptJson()->timeout(10)->get($config['jwks_url'])->throw()->json());
        $claims = (array) JWT::decode($token, JWK::parseKeySet($jwks));
        $issuer = (string) ($claims['iss'] ?? '');
        $validIssuer = $provider === 'google' ? in_array($issuer, ['https://accounts.google.com', 'accounts.google.com'], true) : $issuer === 'https://appleid.apple.com';
        $audiences = (array) ($claims['aud'] ?? []);
        if (! $validIssuer || array_intersect($clientIds, $audiences) === []) {
            throw new RuntimeException('Invalid token issuer or audience.');
        }
        if ($nonce !== null && ! hash_equals($nonce, (string) ($claims['nonce'] ?? ''))) {
            throw new RuntimeException('Invalid social token nonce.');
        }
        if (empty($claims['sub'])) {
            throw new RuntimeException('Social token has no subject.');
        }
        if (isset($claims['email_verified']) && ! in_array($claims['email_verified'], [true, 'true', 1, '1'], true)) {
            throw new RuntimeException('Social email is not verified.');
        }

        return ['subject' => (string) $claims['sub'], 'email' => isset($claims['email']) ? mb_strtolower((string) $claims['email']) : null, 'name' => $claims['name'] ?? null];
    }
}
