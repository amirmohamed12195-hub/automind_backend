<?php

namespace App\Services\Notifications;

use App\Contracts\FcmAccessTokenProvider;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;

class GoogleFcmAccessTokenProvider implements FcmAccessTokenProvider
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function accessToken(): string
    {
        $credentials = $this->credentials();
        $cacheKey = $this->cacheKey($credentials);
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $now = time();
        $assertion = JWT::encode([
            'iss' => $credentials['client_email'],
            'sub' => $credentials['client_email'],
            'aud' => $credentials['token_uri'],
            'scope' => self::SCOPE,
            'iat' => $now,
            'exp' => $now + 3600,
        ], $credentials['private_key'], 'RS256', $credentials['private_key_id']);

        $response = Http::asForm()
            ->acceptJson()
            ->timeout((int) config('services.fcm.timeout_seconds', 15))
            ->post($credentials['token_uri'], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("Unable to authorize FCM (HTTP {$response->status()}).");
        }

        $accessToken = $response->json('access_token');
        $expiresIn = (int) $response->json('expires_in', 3600);
        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Google OAuth response did not contain an access token.');
        }

        Cache::put($cacheKey, $accessToken, now()->addSeconds(max(60, $expiresIn - 180)));

        return $accessToken;
    }

    public function forget(): void
    {
        Cache::forget($this->cacheKey($this->credentials()));
    }

    /** @return array{client_email:string,private_key:string,private_key_id:string,project_id:string,token_uri:string} */
    private function credentials(): array
    {
        $encoded = trim((string) config('services.fcm.credentials_base64'));
        if ($encoded !== '') {
            $json = base64_decode($encoded, true);
            if ($json === false) {
                throw new RuntimeException('FCM_CREDENTIALS_BASE64 is not valid base64.');
            }
        } else {
            $path = trim((string) config('services.fcm.credentials_path'));
            if ($path === '') {
                throw new RuntimeException('FCM service-account credentials are not configured.');
            }
            if (! str_starts_with($path, DIRECTORY_SEPARATOR)) {
                $path = base_path($path);
            }
            if (! is_file($path) || ! is_readable($path)) {
                throw new RuntimeException('The configured FCM credentials file is not readable.');
            }
            $json = file_get_contents($path);
            if ($json === false) {
                throw new RuntimeException('Unable to read the configured FCM credentials file.');
            }
        }

        try {
            $credentials = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The FCM credentials JSON is invalid.', previous: $exception);
        }

        if (! is_array($credentials)) {
            throw new RuntimeException('The FCM credentials must be a JSON object.');
        }
        foreach (['client_email', 'private_key', 'private_key_id', 'project_id', 'token_uri'] as $key) {
            if (! isset($credentials[$key]) || ! is_string($credentials[$key]) || $credentials[$key] === '') {
                throw new RuntimeException("The FCM credentials are missing {$key}.");
            }
        }
        if (($credentials['type'] ?? null) !== 'service_account') {
            throw new RuntimeException('The FCM credentials are not a service account.');
        }

        $configuredProject = trim((string) config('services.fcm.project_id'));
        if ($configuredProject !== '' && ! hash_equals($configuredProject, $credentials['project_id'])) {
            throw new RuntimeException('The FCM project ID does not match the service-account credentials.');
        }

        /** @var array{client_email:string,private_key:string,private_key_id:string,project_id:string,token_uri:string} $credentials */
        return $credentials;
    }

    /** @param array{private_key_id:string,client_email:string} $credentials */
    private function cacheKey(array $credentials): string
    {
        return 'fcm:oauth:'.hash('sha256', $credentials['client_email'].'|'.$credentials['private_key_id']);
    }
}
