<?php

namespace App\Services\Billing;

use App\Exceptions\BillingException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class KashierClient
{
    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createSession(array $payload): array
    {
        return $this->request('post', '/v3/payment/sessions', $payload);
    }

    /** @return array<string, mixed> */
    public function session(string $id): array
    {
        return $this->request('get', '/v3/payment/sessions/'.rawurlencode($id));
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $payload = []): array
    {
        $mode = config('kashier.mode');
        if (! in_array($mode, ['test', 'live'], true)
            || ! config('kashier.merchant_id') || ! config('kashier.api_key') || ! config('kashier.secret_key')) {
            throw new BillingException('KASHIER_NOT_CONFIGURED', 'Website checkout is temporarily unavailable.', 503);
        }
        $base = $mode === 'live' ? 'https://api.kashier.io' : 'https://test-api.kashier.io';
        try {
            // Never retry session creation: a timeout may still have created it.
            $response = Http::acceptJson()->asJson()->connectTimeout(5)->timeout(20)
                ->withHeaders(['Authorization' => config('kashier.secret_key'), 'api-key' => config('kashier.api_key')])
                ->{$method}($base.$path, $payload);
        } catch (ConnectionException) {
            throw new BillingException('KASHIER_UNAVAILABLE', 'The payment provider is temporarily unavailable. Please try again later.', 503, true);
        }
        $body = $response->json();
        if (! $response->successful() || ! is_array($body)) {
            // Provider bodies may include credentials and customer data.
            throw new BillingException('KASHIER_UNAVAILABLE', 'The payment provider is temporarily unavailable. Please try again later.', 503, true);
        }

        return $body;
    }

    /** @param array<string, mixed> $data */
    public function verifyWebhook(array $data, string $signature): bool
    {
        $keys = $data['signatureKeys'] ?? null;
        $required = ['amount', 'currency', 'merchantOrderId', 'kashierOrderId', 'status', 'transactionId', 'transactionResponseCode'];
        if (! is_array($keys) || ! array_is_list($keys) || count($keys) > 30
            || count(array_filter($keys, 'is_string')) !== count($keys)
            || array_diff($required, $keys) !== [] || ! preg_match('/\A[0-9a-f]{64}\z/i', $signature)
            || ! is_string(config('kashier.api_key')) || config('kashier.api_key') === '') {
            return false;
        }
        sort($keys, SORT_STRING);
        $signed = [];
        foreach ($keys as $key) {
            if (! array_key_exists($key, $data) || ! is_scalar($data[$key])) {
                return false;
            }
            $signed[$key] = $data[$key];
        }
        $expected = hash_hmac('sha256', http_build_query($signed, '', '&', PHP_QUERY_RFC3986), config('kashier.api_key'));

        return hash_equals($expected, strtolower($signature));
    }
}
