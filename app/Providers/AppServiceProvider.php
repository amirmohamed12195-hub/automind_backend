<?php

namespace App\Providers;

use App\Contracts\AiDiagnosticProvider;
use App\Contracts\AppleStoreProvider;
use App\Contracts\AudioUnderstandingProvider;
use App\Contracts\CurrencyRateProvider;
use App\Contracts\FcmAccessTokenProvider;
use App\Contracts\GeocodingProvider;
use App\Contracts\GooglePlayProvider;
use App\Contracts\ObjectStorageProvider;
use App\Contracts\PushNotificationProvider;
use App\Contracts\SpeechTranscriptionProvider;
use App\Contracts\VisionUnderstandingProvider;
use App\Contracts\WebPriceSearchProvider;
use App\Models\User;
use App\Services\Ai\OpenAiAudioUnderstandingProvider;
use App\Services\Ai\OpenAiConfigurationValidator;
use App\Services\Ai\OpenAiDiagnosticProvider;
use App\Services\Ai\OpenAiSpeechTranscriptionProvider;
use App\Services\Ai\OpenAiVisionUnderstandingProvider;
use App\Services\Ai\OpenAiWebPriceSearchProvider;
use App\Services\Billing\AppleAppStoreProvider;
use App\Services\Billing\BillingConfigurationValidator;
use App\Services\Billing\GooglePlayDeveloperProvider;
use App\Services\Geocoding\HttpGeocodingProvider;
use App\Services\Notifications\FcmPushNotificationProvider;
use App\Services\Notifications\GoogleFcmAccessTokenProvider;
use App\Services\Pricing\DatabaseCurrencyRateProvider;
use App\Services\Storage\LaravelObjectStorageProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AiDiagnosticProvider::class, OpenAiDiagnosticProvider::class);
        $this->app->bind(AudioUnderstandingProvider::class, OpenAiAudioUnderstandingProvider::class);
        $this->app->bind(SpeechTranscriptionProvider::class, OpenAiSpeechTranscriptionProvider::class);
        $this->app->bind(VisionUnderstandingProvider::class, OpenAiVisionUnderstandingProvider::class);
        $this->app->bind(WebPriceSearchProvider::class, OpenAiWebPriceSearchProvider::class);
        $this->app->bind(ObjectStorageProvider::class, LaravelObjectStorageProvider::class);
        $this->app->bind(CurrencyRateProvider::class, DatabaseCurrencyRateProvider::class);
        $this->app->bind(PushNotificationProvider::class, FcmPushNotificationProvider::class);
        $this->app->singleton(FcmAccessTokenProvider::class, GoogleFcmAccessTokenProvider::class);
        $this->app->bind(GeocodingProvider::class, HttpGeocodingProvider::class);
        $this->app->bind(AppleStoreProvider::class, AppleAppStoreProvider::class);
        $this->app->bind(GooglePlayProvider::class, GooglePlayDeveloperProvider::class);
    }

    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (User $user, string $token): string {
            return URL::route('password.reset.show', [
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
                'lang' => in_array($user->locale, ['en', 'ar'], true) ? $user->locale : 'en',
            ]);
        });

        $consoleCommand = $this->app->runningInConsole() ? ($_SERVER['argv'][1] ?? null) : null;
        $providerRuntime = ! $this->app->runningInConsole() || in_array($consoleCommand, ['queue:work', 'queue:listen', 'horizon', 'octane:start'], true);
        if ($this->app->environment('production') && $providerRuntime) {
            $this->app->make(OpenAiConfigurationValidator::class)->validate();
            $this->app->make(BillingConfigurationValidator::class)->validate();
        }
        foreach (['login' => 8, 'admin-login' => 5, 'password-reset' => 5, 'uploads' => 30, 'analysis' => 10, 'web-search' => 5, 'appointments' => 12, 'feedback' => 20, 'billing' => 60] as $name => $perMinute) {
            RateLimiter::for($name, function (Request $request) use ($perMinute) {
                $user = $request->user();

                return Limit::perMinute($perMinute)->by((string) ($user instanceof User ? $user->id : $request->ip()));
            });
        }
    }
}
