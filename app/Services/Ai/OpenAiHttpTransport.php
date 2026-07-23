<?php

namespace App\Services\Ai;

use App\Exceptions\AiProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class OpenAiHttpTransport
{
    public function post(string $path, array $payload): array
    {
        try {
            $response = $this->client()->post($path, $payload);
        } catch (ConnectionException) {
            throw new AiProviderException('The OpenAI API connection timed out or failed.', 'connection', true);
        }

        return $this->decode($response);
    }

    public function multipart(string $path, array $parts): array
    {
        $request = $this->client()->asMultipart();
        foreach ($parts as $part) {
            $request = isset($part['contents']) && is_resource($part['contents'])
                ? $request->attach($part['name'], $part['contents'], $part['filename'] ?? null, $part['headers'] ?? [])
                : $request->attach($part['name'], (string) ($part['contents'] ?? ''));
        }

        try {
            return $this->decode($request->post($path));
        } catch (ConnectionException) {
            throw new AiProviderException('The OpenAI API connection timed out or failed.', 'connection', true);
        }
    }

    private function client(): PendingRequest
    {
        $key = (string) config('openai.api_key');
        if ($key === '') {
            throw new AiProviderException('OpenAI API is not configured.', 'configuration', false);
        }

        return Http::baseUrl((string) config('openai.base_url'))
            ->withToken($key)->acceptJson()->timeout((int) config('openai.timeout_seconds'))
            ->connectTimeout(min(20, (int) config('openai.timeout_seconds')));
    }

    private function decode(Response $response): array
    {
        if (! $response->successful()) {
            $status = $response->status();
            $transient = $status === 429 || $status >= 500;
            $category = $status === 429 ? 'rate_limit' : ($status >= 500 ? 'provider_unavailable' : (in_array($status, [401, 403], true) ? 'authentication' : 'provider_rejected'));
            $message = match ($category) {
                'rate_limit' => 'The OpenAI API rate limit was reached.',
                'provider_unavailable' => 'The OpenAI API is temporarily unavailable.',
                'authentication' => 'The OpenAI API rejected the configured credentials.',
                default => 'The OpenAI API rejected the request.',
            };
            throw new AiProviderException($message, $category, $transient, $this->retryAfterSeconds($response));
        }
        $json = $response->json();
        if (! is_array($json)) {
            throw new AiProviderException('OpenAI API returned invalid JSON.', 'invalid_response');
        }

        return $json;
    }

    private function retryAfterSeconds(Response $response): ?int
    {
        $header = trim((string) $response->header('Retry-After'));
        if ($header === '') {
            return null;
        }
        if (ctype_digit($header)) {
            return max(1, (int) $header);
        }
        $timestamp = strtotime($header);

        return $timestamp === false ? null : max(1, $timestamp - time());
    }
}
