<?php

namespace Tests\Unit;

use App\Services\Auth\SocialIdentityVerifier;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class SocialIdentityVerifierTest extends TestCase
{
    public function test_apple_nonce_is_compared_using_its_sha256_digest(): void
    {
        [$privateKey, $jwk] = $this->rsaKeyPair();
        $rawNonce = 'a-cryptographically-random-client-nonce';
        $token = JWT::encode([
            'iss' => 'https://appleid.apple.com',
            'aud' => 'com.automind.ai',
            'sub' => 'apple-user-1',
            'email' => 'driver@example.com',
            'email_verified' => 'true',
            'nonce' => hash('sha256', $rawNonce),
            'iat' => now()->timestamp,
            'exp' => now()->addMinutes(5)->timestamp,
        ], $privateKey, 'RS256', 'test-key');

        config([
            'services.apple.client_ids' => ['com.automind.ai'],
            'services.apple.jwks_url' => 'https://apple.test/keys',
        ]);
        Http::fake(['apple.test/keys' => Http::response(['keys' => [$jwk]])]);

        $identity = app(SocialIdentityVerifier::class)->verify('apple', $token, $rawNonce);

        $this->assertSame('apple-user-1', $identity['subject']);
        $this->assertSame('driver@example.com', $identity['email']);
    }

    public function test_apple_rejects_missing_or_incorrect_raw_nonce(): void
    {
        [$privateKey, $jwk] = $this->rsaKeyPair();
        $token = JWT::encode([
            'iss' => 'https://appleid.apple.com',
            'aud' => 'com.automind.ai',
            'sub' => 'apple-user-1',
            'nonce' => hash('sha256', 'correct-raw-nonce'),
            'iat' => now()->timestamp,
            'exp' => now()->addMinutes(5)->timestamp,
        ], $privateKey, 'RS256', 'test-key');

        config([
            'services.apple.client_ids' => ['com.automind.ai'],
            'services.apple.jwks_url' => 'https://apple.test/keys',
        ]);
        Http::fake(['apple.test/keys' => Http::response(['keys' => [$jwk]])]);

        $this->expectException(RuntimeException::class);
        app(SocialIdentityVerifier::class)->verify('apple', $token, 'incorrect-raw-nonce');
    }

    public function test_social_email_must_have_an_explicit_verified_claim(): void
    {
        [$privateKey, $jwk] = $this->rsaKeyPair();
        $token = JWT::encode([
            'iss' => 'https://accounts.google.com',
            'aud' => 'google-server-client-id',
            'sub' => 'google-user-1',
            'email' => 'driver@example.com',
            'iat' => now()->timestamp,
            'exp' => now()->addMinutes(5)->timestamp,
        ], $privateKey, 'RS256', 'test-key');

        config([
            'services.google.client_ids' => ['google-server-client-id'],
            'services.google.jwks_url' => 'https://google.test/keys',
        ]);
        Http::fake(['google.test/keys' => Http::response(['keys' => [$jwk]])]);

        $this->expectException(RuntimeException::class);
        app(SocialIdentityVerifier::class)->verify('google', $token);
    }

    /** @return array{0: string, 1: array<string, string>} */
    private function rsaKeyPair(): array
    {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $this->assertNotFalse($resource);
        $exported = openssl_pkey_export($resource, $privateKey);
        $this->assertTrue($exported);
        $details = openssl_pkey_get_details($resource);
        $this->assertIsArray($details);

        return [
            $privateKey,
            [
                'kty' => 'RSA',
                'kid' => 'test-key',
                'use' => 'sig',
                'alg' => 'RS256',
                'n' => $this->base64Url($details['rsa']['n']),
                'e' => $this->base64Url($details['rsa']['e']),
            ],
        ];
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
