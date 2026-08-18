<?php

namespace App\Services\Auth;

use App\Contracts\PhoneVerificationProvider;
use App\Exceptions\PhoneVerificationException;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class PhoneVerificationService
{
    public function __construct(private readonly PhoneVerificationProvider $provider) {}

    public function begin(User $user, string $purpose): array
    {
        $this->assertPending($user);
        if ($this->retryAfter($user) === 0) {
            $this->send($user);
        }

        return $this->challenge($user, $purpose);
    }

    public function resend(string $token): array
    {
        [$user, $purpose] = $this->resolve($token);
        $this->assertPending($user);
        $retryAfter = $this->retryAfter($user);
        if ($retryAfter > 0) {
            throw new PhoneVerificationException(
                'OTP_RESEND_TOO_SOON',
                __('api.otp_resend_too_soon'),
                429,
                ['retryAfterSeconds' => [(string) $retryAfter]],
            );
        }
        $this->send($user);

        return $this->challenge($user, $purpose);
    }

    public function verify(string $token, string $code): User
    {
        [$user] = $this->resolve($token);
        $this->assertPending($user);
        if (! $this->provider->check((string) $user->phone, $code)) {
            throw new PhoneVerificationException(
                'OTP_INVALID',
                __('api.otp_invalid'),
                422,
            );
        }

        $user = DB::transaction(function () use ($user): User {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $this->assertPending($locked);
            $locked->forceFill(['phone_verified_at' => now()])->save();

            return $locked->fresh();
        });
        Cache::forget($this->cooldownKey($user));

        return $user;
    }

    private function send(User $user): void
    {
        $this->provider->start((string) $user->phone);
        Cache::put(
            $this->cooldownKey($user),
            now()->addSeconds($this->cooldownSeconds())->getTimestamp(),
            $this->cooldownSeconds(),
        );
    }

    private function challenge(User $user, string $purpose): array
    {
        $expiresIn = max(60, (int) config('services.twilio.otp.code_ttl_seconds', 600));
        $tokenTtl = max($expiresIn, (int) config('services.twilio.otp.challenge_ttl_seconds', 1800));
        $payload = [
            'userId' => (string) $user->id,
            'phoneHash' => hash('sha256', (string) $user->phone),
            'purpose' => $purpose,
            'expiresAt' => now()->addSeconds($tokenTtl)->getTimestamp(),
        ];

        return [
            'verificationRequired' => true,
            'verificationToken' => Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)),
            'maskedPhone' => $this->mask((string) $user->phone),
            'expiresInSeconds' => $expiresIn,
            'resendAfterSeconds' => max($this->retryAfter($user), 0),
            'purpose' => $purpose,
        ];
    }

    private function resolve(string $token): array
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, 8, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            throw $this->expired();
        }

        $userId = is_array($payload) ? ($payload['userId'] ?? null) : null;
        $expiresAt = is_array($payload) ? ($payload['expiresAt'] ?? null) : null;
        $phoneHash = is_array($payload) ? ($payload['phoneHash'] ?? null) : null;
        $purpose = is_array($payload) ? ($payload['purpose'] ?? null) : null;
        if (! is_string($userId) || ! is_numeric($expiresAt) || ! in_array($purpose, ['registration', 'login'], true)) {
            throw $this->expired();
        }

        $user = User::query()->find($userId);
        if (! $user || (int) $expiresAt < now()->getTimestamp() || ! hash_equals((string) $phoneHash, hash('sha256', (string) $user->phone))) {
            throw $this->expired();
        }

        return [$user, $purpose];
    }

    private function assertPending(User $user): void
    {
        if (! is_string($user->phone) || ! preg_match('/^\+[1-9]\d{7,14}$/', $user->phone)) {
            throw new PhoneVerificationException('OTP_PHONE_MISSING', __('api.otp_phone_missing'), 409);
        }
        if ($user->phone_verified_at !== null) {
            throw new PhoneVerificationException('OTP_ALREADY_VERIFIED', __('api.otp_already_verified'), 409);
        }
    }

    private function retryAfter(User $user): int
    {
        $availableAt = (int) Cache::get($this->cooldownKey($user), 0);

        return max(0, $availableAt - now()->getTimestamp());
    }

    private function cooldownKey(User $user): string
    {
        return 'phone-verification:resend:'.$user->id;
    }

    private function cooldownSeconds(): int
    {
        return max(1, (int) config('services.twilio.otp.resend_cooldown_seconds', 30));
    }

    private function mask(string $phone): string
    {
        if (strlen($phone) <= 7) {
            return $phone;
        }

        return substr($phone, 0, 3).str_repeat('•', max(3, strlen($phone) - 7)).substr($phone, -4);
    }

    private function expired(): PhoneVerificationException
    {
        return new PhoneVerificationException('OTP_CHALLENGE_EXPIRED', __('api.otp_challenge_expired'), 422);
    }
}
