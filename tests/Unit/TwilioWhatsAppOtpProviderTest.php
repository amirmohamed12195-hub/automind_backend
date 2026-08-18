<?php

namespace Tests\Unit;

use App\Exceptions\PhoneVerificationException;
use App\Services\Auth\TwilioWhatsAppOtpProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TwilioWhatsAppOtpProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
            'services.twilio.whatsapp.enabled' => true,
            'services.twilio.whatsapp.from' => '+14155238886',
            'services.twilio.whatsapp.content_sid' => 'HX'.str_repeat('a', 32),
            'services.twilio.account_sid' => 'AC'.str_repeat('b', 32),
            'services.twilio.api_key' => 'SK'.str_repeat('c', 32),
            'services.twilio.api_secret' => 'api-secret',
            'services.twilio.auth_token' => null,
            'services.twilio.otp.code_ttl_seconds' => 600,
            'services.twilio.otp.max_attempts' => 5,
        ]);
    }

    public function test_it_sends_a_template_and_validates_the_generated_code_once(): void
    {
        $sentCode = null;
        Http::fake(function (Request $request) use (&$sentCode) {
            $variables = json_decode((string) $request['ContentVariables'], true, 8, JSON_THROW_ON_ERROR);
            $sentCode = $variables['1'] ?? null;

            return Http::response(['sid' => 'MM'.str_repeat('d', 32), 'status' => 'queued'], 201);
        });
        $provider = app(TwilioWhatsAppOtpProvider::class);

        $provider->start('+201001234567');

        $this->assertIsString($sentCode);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $sentCode);
        $wrongCode = $sentCode === '000000' ? '999999' : '000000';
        $this->assertFalse($provider->check('+201001234567', $wrongCode));
        $this->assertTrue($provider->check('+201001234567', $sentCode));
        $this->assertFalse($provider->check('+201001234567', $sentCode));

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC'.str_repeat('b', 32).'/Messages.json'
            && $request['To'] === 'whatsapp:+201001234567'
            && $request['From'] === 'whatsapp:+14155238886'
            && $request['ContentSid'] === 'HX'.str_repeat('a', 32)
            && ! isset($request['Body']));
    }

    public function test_it_invalidates_the_code_after_the_attempt_limit(): void
    {
        $sentCode = null;
        config(['services.twilio.otp.max_attempts' => 2]);
        Http::fake(function (Request $request) use (&$sentCode) {
            $sentCode = json_decode((string) $request['ContentVariables'], true, 8, JSON_THROW_ON_ERROR)['1'];

            return Http::response(['sid' => 'SM'.str_repeat('d', 32)], 201);
        });
        $provider = app(TwilioWhatsAppOtpProvider::class);
        $provider->start('+201001234567');
        $wrongCode = $sentCode === '000000' ? '999999' : '000000';

        $this->assertFalse($provider->check('+201001234567', $wrongCode));
        $this->assertFalse($provider->check('+201001234567', $wrongCode));
        $this->assertFalse($provider->check('+201001234567', $sentCode));
    }

    public function test_it_fails_closed_when_whatsapp_is_not_configured(): void
    {
        config(['services.twilio.whatsapp.enabled' => false]);

        try {
            app(TwilioWhatsAppOtpProvider::class)->start('+201001234567');
            $this->fail('Expected the WhatsApp OTP provider to reject incomplete configuration.');
        } catch (PhoneVerificationException $exception) {
            $this->assertSame('OTP_SERVICE_NOT_CONFIGURED', $exception->errorCode);
        }

        Http::assertNothingSent();
    }

    public function test_it_rejects_the_documentation_auth_token_placeholder(): void
    {
        config([
            'services.twilio.api_key' => null,
            'services.twilio.api_secret' => null,
            'services.twilio.auth_token' => '[AuthToken]',
        ]);

        try {
            app(TwilioWhatsAppOtpProvider::class)->start('+201001234567');
            $this->fail('Expected the WhatsApp OTP provider to reject a placeholder Auth Token.');
        } catch (PhoneVerificationException $exception) {
            $this->assertSame('OTP_SERVICE_NOT_CONFIGURED', $exception->errorCode);
        }

        Http::assertNothingSent();
    }
}
