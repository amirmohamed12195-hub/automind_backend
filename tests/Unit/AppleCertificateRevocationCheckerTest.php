<?php

namespace Tests\Unit;

use App\Exceptions\BillingException;
use App\Services\Billing\AppleCertificateRevocationChecker;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class AppleCertificateRevocationCheckerTest extends TestCase
{
    public function test_online_check_can_be_explicitly_disabled_for_offline_development(): void
    {
        config(['billing.apple.online_certificate_checks' => false]);

        app(AppleCertificateRevocationChecker::class)->assertNotRevoked(['not-a-certificate'], []);

        $this->assertTrue(true);
    }

    public function test_online_check_fails_closed_without_an_issuer(): void
    {
        config(['billing.apple.online_certificate_checks' => true]);

        try {
            app(AppleCertificateRevocationChecker::class)->assertNotRevoked(['leaf-only'], []);
            $this->fail('Expected Apple certificate status checking to fail closed.');
        } catch (BillingException $exception) {
            $this->assertSame('APPLE_CERTIFICATE_STATUS_UNAVAILABLE', $exception->errorCode);
            $this->assertSame(503, $exception->httpStatus);
            $this->assertTrue($exception->retryable);
        }
    }

    public function test_verified_good_ocsp_response_is_accepted(): void
    {
        config([
            'billing.apple.online_certificate_checks' => true,
            'billing.apple.openssl_binary' => 'openssl-test',
        ]);
        Process::fake([
            '*' => Process::result(output: "Response verify OK\ncertificate.pem: good\n"),
        ]);

        $this->checkerWithOcspUrl()->assertNotRevoked(['leaf', 'issuer'], ['root']);

        Process::assertRan(fn ($process): bool => in_array('ocsp', $process->command, true));
    }

    public function test_revoked_ocsp_response_is_rejected(): void
    {
        config(['billing.apple.online_certificate_checks' => true]);
        Process::fake([
            '*' => Process::result(output: "Response verify OK\ncertificate.pem: revoked\n"),
        ]);

        $this->expectExceptionObject(
            new BillingException(
                'PURCHASE_VERIFICATION_FAILED',
                'Apple signing certificate has been revoked.',
            ),
        );

        $this->checkerWithOcspUrl()->assertNotRevoked(['leaf', 'issuer'], ['root']);
    }

    private function checkerWithOcspUrl(): AppleCertificateRevocationChecker
    {
        return new class extends AppleCertificateRevocationChecker
        {
            protected function ocspUrl(string $certificate): ?string
            {
                return 'https://ocsp.apple.test';
            }
        };
    }
}
