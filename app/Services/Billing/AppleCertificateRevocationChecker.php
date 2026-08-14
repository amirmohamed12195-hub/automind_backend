<?php

namespace App\Services\Billing;

use App\Exceptions\BillingException;
use Illuminate\Support\Facades\Process;

class AppleCertificateRevocationChecker
{
    /**
     * Check each non-root certificate that publishes an OCSP responder. The
     * chain and URL have already been authenticated against an Apple root by
     * AppleSignedDataVerifier before this method is called.
     *
     * @param  array<int, string>  $certificates
     * @param  array<int, string>  $trustedRoots
     */
    public function assertNotRevoked(array $certificates, array $trustedRoots): void
    {
        if (! (bool) config('billing.apple.online_certificate_checks')) {
            return;
        }
        if (count($certificates) < 2) {
            throw $this->unavailable('Apple certificate status cannot be checked without its issuer.');
        }

        $checked = 0;
        for ($index = 0; $index < count($certificates) - 1; $index++) {
            $url = $this->ocspUrl($certificates[$index]);
            if ($url === null) {
                if ($index === 0) {
                    throw $this->unavailable('Apple signing certificate does not publish an OCSP responder.');
                }

                continue;
            }
            $this->checkCertificate(
                $certificates[$index],
                $certificates[$index + 1],
                $trustedRoots,
                $url,
            );
            $checked++;
        }

        if ($checked === 0) {
            throw $this->unavailable('No Apple signing certificate status could be checked.');
        }
    }

    /**
     * @param  array<int, string>  $trustedRoots
     */
    private function checkCertificate(string $certificate, string $issuer, array $trustedRoots, string $url): void
    {
        $paths = [];
        try {
            $certificatePath = $this->temporaryCertificate($certificate, $paths);
            $issuerPath = $this->temporaryCertificate($issuer, $paths);
            $rootsPath = $this->temporaryCertificate(implode("\n", $trustedRoots), $paths);
            $binary = trim((string) config('billing.apple.openssl_binary', 'openssl')) ?: 'openssl';
            $result = Process::timeout(12)->run([
                $binary,
                'ocsp',
                '-issuer', $issuerPath,
                '-cert', $certificatePath,
                '-url', $url,
                '-CAfile', $rootsPath,
                '-verify_other', $issuerPath,
                '-trust_other',
                '-no_nonce',
                '-timeout', '5',
            ]);
            $output = trim($result->output()."\n".$result->errorOutput());

            if (preg_match('/:\s*revoked\b/i', $output) === 1) {
                throw new BillingException(
                    'PURCHASE_VERIFICATION_FAILED',
                    'Apple signing certificate has been revoked.',
                );
            }
            if (! $result->successful()
                || preg_match('/:\s*good\b/i', $output) !== 1
                || stripos($output, 'Response verify OK') === false) {
                throw $this->unavailable('Apple certificate status could not be verified.');
            }
        } finally {
            foreach ($paths as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }

    /** @param array<int, string> $paths */
    private function temporaryCertificate(string $contents, array &$paths): string
    {
        $path = tempnam(sys_get_temp_dir(), 'automind-ocsp-');
        if ($path === false || file_put_contents($path, $contents) === false) {
            throw $this->unavailable('A temporary certificate file could not be created.');
        }
        @chmod($path, 0600);
        $paths[] = $path;

        return $path;
    }

    protected function ocspUrl(string $certificate): ?string
    {
        $parsed = openssl_x509_parse($certificate);
        $authorityInfo = is_array($parsed)
            ? (string) data_get($parsed, 'extensions.authorityInfoAccess', '')
            : '';
        if (preg_match('/(?:OCSP\s*-\s*)?URI:(https?:\/\/[^\s,]+)/i', $authorityInfo, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private function unavailable(string $message): BillingException
    {
        return new BillingException(
            'APPLE_CERTIFICATE_STATUS_UNAVAILABLE',
            $message,
            503,
            true,
        );
    }
}
