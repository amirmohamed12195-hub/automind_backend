<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\SocialIdentity;
use App\Models\User;
use App\Services\Auth\SocialIdentityVerifier;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

class AuthController
{
    public function register(RegisterRequest $request)
    {
        $user = User::query()->create(['name' => $request->string('name'), 'email' => $request->string('email'), 'password' => $request->string('password'), 'locale' => $request->input('locale', 'en')]);

        return ApiResponse::success(['user' => (new UserResource($user))->resolve(), 'accessToken' => $user->createToken($request->input('deviceName', 'AutoMind mobile'))->plainTextToken, 'tokenType' => 'Bearer'], 201);
    }

    public function login(LoginRequest $request)
    {
        $user = User::query()->where('email', $request->string('email'))->first();
        if (! $user || ! $user->password || ! Hash::check($request->string('password'), $user->password)) {
            return ApiResponse::error('INVALID_CREDENTIALS', __('api.invalid_credentials'), 401);
        }
        $user->forceFill(['last_login_at' => now()])->save();

        return ApiResponse::success(['user' => (new UserResource($user))->resolve(), 'accessToken' => $user->createToken($request->input('deviceName', 'AutoMind mobile'))->plainTextToken, 'tokenType' => 'Bearer']);
    }

    public function social(Request $request, string $provider, SocialIdentityVerifier $verifier)
    {
        $data = $request->validate([
            'identityToken' => ['required', 'string'],
            'nonce' => [$provider === 'apple' ? 'required' : 'nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:120'],
            'deviceName' => ['nullable', 'string', 'max:120'],
        ]);
        try {
            $identity = $verifier->verify($provider, $data['identityToken'], $data['nonce'] ?? null);
        } catch (Throwable) {
            return ApiResponse::error('SOCIAL_IDENTITY_INVALID', __('api.social_invalid'), 401);
        }
        $knownIdentity = SocialIdentity::query()->where('provider', $provider)->where('provider_subject', $identity['subject'])->exists();
        if (! $knownIdentity && ! $identity['email']) {
            return ApiResponse::error('SOCIAL_EMAIL_REQUIRED', __('api.social_email_required'), 422);
        }
        $user = DB::transaction(function () use ($data, $identity, $provider) {
            $social = SocialIdentity::query()
                ->where('provider', $provider)
                ->where('provider_subject', $identity['subject'])
                ->lockForUpdate()
                ->first();
            if ($social) {
                return User::query()->findOrFail($social->user_id);
            }

            $user = User::query()->firstOrCreate(
                ['email' => $identity['email']],
                [
                    'name' => $identity['name'] ?: ($data['name'] ?? null) ?: Str::before($identity['email'], '@'),
                    'locale' => app()->getLocale(),
                    'email_verified_at' => now(),
                ],
            );
            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
            $social = SocialIdentity::query()->firstOrCreate(
                ['provider' => $provider, 'provider_subject' => $identity['subject']],
                ['user_id' => $user->id, 'provider_email' => $identity['email']],
            );

            return $social->user_id === $user->id
                ? $user
                : User::query()->findOrFail($social->user_id);
        });
        $user->forceFill(['last_login_at' => now()])->save();

        return ApiResponse::success(['user' => (new UserResource($user))->resolve(), 'accessToken' => $user->createToken($data['deviceName'] ?? 'AutoMind mobile')->plainTextToken, 'tokenType' => 'Bearer']);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => ['required', 'email:rfc']]);
        Password::sendResetLink(['email' => mb_strtolower((string) $request->input('email'))]);

        return ApiResponse::success(['message' => __('api.password_reset_link_sent')]);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email:rfc'], 'token' => ['required', 'string'], 'password' => ['required', 'confirmed', 'min:8']]);
        $status = Password::reset($data, function (User $user, string $password): void {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete();
        });
        if ($status !== Password::PASSWORD_RESET) {
            return ApiResponse::error('PASSWORD_RESET_FAILED', __($status), 422);
        }

        return ApiResponse::success(['message' => __($status)]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }

    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->noContent();
    }
}
