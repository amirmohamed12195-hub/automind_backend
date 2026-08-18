<?php

namespace App\Services\Auth;

use App\Contracts\PhoneVerificationProvider;
use App\Exceptions\PhoneVerificationException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class TwilioWhatsAppOtpProvider implements PhoneVerificationProvider
{
    public function start(string $phone): void
    {
        try {
            $configuration = $this->configuration();
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $response = $this->request()->post($this->messagesUrl($configuration['accountSid']), [
                'To' => 'whatsapp:'.$phone,
                'From' => $configuration['from'],
                'ContentSid' => $configuration['contentSid'],
                'ContentVariables' => json_encode(['1' => $code], JSON_THROW_ON_ERROR),
            ]);

            $messageSid = $response->json('sid');
            if (! $response->successful() || ! is_string($messageSid) || ! preg_match('/^(?:SM|MM)[0-9a-f]{32}$/i', $messageSid)) {
                throw $this->providerFailure();
            }

            $ttl = $this->codeTtlSeconds();
            Cache::put($this->codeKey($phone), [
                'hash' => $this->codeHash($phone, $code),
                'attempts' => 0,
                'expiresAt' => now()->addSeconds($ttl)->getTimestamp(),
            ], $ttl);
        } catch (PhoneVerificationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw $this->providerFailure();
        }
    }

    public function check(string $phone, string $code): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        try {
            return Cache::lock($this->lockKey($phone), 5)->block(3, function () use ($phone, $code): bool {
                $state = Cache::get($this->codeKey($phone));
                if (! is_array($state)
                    || ! is_string($state['hash'] ?? null)
                    || ! is_numeric($state['attempts'] ?? null)
                    || ! is_numeric($state['expiresAt'] ?? null)
                ) {
                    return false;
                }

                $expiresAt = (int) $state['expiresAt'];
                if ($expiresAt <= now()->getTimestamp()) {
                    Cache::forget($this->codeKey($phone));

                    return false;
                }

                $attempts = (int) $state['attempts'] + 1;
                if (hash_equals($state['hash'], $this->codeHash($phone, $code))) {
                    Cache::forget($this->codeKey($phone));

                    return true;
                }

                if ($attempts >= $this->maxAttempts()) {
                    Cache::forget($this->codeKey($phone));
                } else {
                    $state['attempts'] = $attempts;
                    Cache::put($this->codeKey($phone), $state, max(1, $expiresAt - now()->getTimestamp()));
                }

                return false;
            });
        } catch (PhoneVerificationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw $this->providerFailure();
        }
    }

    private function request(): PendingRequest
    {
        [$username, $password] = $this->credentials();

        return Http::asForm()
            ->acceptJson()
            ->withBasicAuth($username, $password)
            ->timeout(max(1, (int) config('services.twilio.whatsapp.timeout_seconds', 10)));
    }

    /** @return array{accountSid: string, from: string, contentSid: string} */
    private function configuration(): array
    {
        if (! (bool) config('services.twilio.whatsapp.enabled')) {
            throw $this->configurationFailure();
        }

        $accountSid = trim((string) config('services.twilio.account_sid'));
        $from = trim((string) config('services.twilio.whatsapp.from'));
        $contentSid = trim((string) config('services.twilio.whatsapp.content_sid'));
        $from = str_starts_with($from, 'whatsapp:') ? substr($from, 9) : $from;

        if (! preg_match('/^AC[0-9a-f]{32}$/i', $accountSid)
            || ! preg_match('/^\+[1-9]\d{7,14}$/', $from)
            || ! preg_match('/^HX[0-9a-f]{32}$/i', $contentSid)
        ) {
            throw $this->configurationFailure();
        }

        return [
            'accountSid' => $accountSid,
            'from' => 'whatsapp:'.$from,
            'contentSid' => $contentSid,
        ];
    }

    /** @return array{string, string} */
    private function credentials(): array
    {
        $apiKey = trim((string) config('services.twilio.api_key'));
        $apiSecret = trim((string) config('services.twilio.api_secret'));
        if ($apiKey !== '' || $apiSecret !== '') {
            if (preg_match('/^SK[0-9a-f]{32}$/i', $apiKey) && $this->isCredentialValue($apiSecret)) {
                return [$apiKey, $apiSecret];
            }

            throw $this->configurationFailure();
        }

        $accountSid = trim((string) config('services.twilio.account_sid'));
        $authToken = trim((string) config('services.twilio.auth_token'));
        if (preg_match('/^AC[0-9a-f]{32}$/i', $accountSid) && $this->isCredentialValue($authToken)) {
            return [$accountSid, $authToken];
        }

        throw $this->configurationFailure();
    }

    private function messagesUrl(string $accountSid): string
    {
        return 'https://api.twilio.com/2010-04-01/Accounts/'.rawurlencode($accountSid).'/Messages.json';
    }

    private function codeHash(string $phone, string $code): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw $this->configurationFailure();
        }

        return hash_hmac('sha256', $phone.':'.$code, $key);
    }

    private function codeKey(string $phone): string
    {
        return 'phone-verification:whatsapp:code:'.hash('sha256', $phone);
    }

    private function lockKey(string $phone): string
    {
        return 'phone-verification:whatsapp:lock:'.hash('sha256', $phone);
    }

    private function codeTtlSeconds(): int
    {
        return max(60, (int) config('services.twilio.otp.code_ttl_seconds', 600));
    }

    private function maxAttempts(): int
    {
        return max(1, (int) config('services.twilio.otp.max_attempts', 5));
    }

    private function isCredentialValue(string $value): bool
    {
        $normalized = strtoupper($value);

        return $value !== ''
            && ! str_contains($normalized, 'CHANGE_ME')
            && ! str_contains($normalized, 'REPLACE_WITH')
            && ! str_contains($normalized, 'YOUR_')
            && ! preg_match('/^\[[^]]+]$/', $value);
    }

    private function configurationFailure(): PhoneVerificationException
    {
        return new PhoneVerificationException(
            'OTP_SERVICE_NOT_CONFIGURED',
            __('api.otp_service_unavailable'),
            503,
        );
    }

    private function providerFailure(): PhoneVerificationException
    {
        return new PhoneVerificationException(
            'OTP_DELIVERY_FAILED',
            __('api.otp_delivery_failed'),
            503,
        );
    }
}
