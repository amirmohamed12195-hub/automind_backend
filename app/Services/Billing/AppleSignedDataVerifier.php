<?php

namespace App\Services\Billing;

use App\Exceptions\BillingException;

class AppleSignedDataVerifier
{
    /** @return array<string, mixed> */
    public function verify(string $jws): array
    {
        $parts = explode('.', $jws);
        if (count($parts) !== 3) {
            throw new BillingException('PURCHASE_VERIFICATION_FAILED', 'Apple signed data is malformed.');
        }
        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $header = $this->decodeJson($encodedHeader);
        $payload = $this->decodeJson($encodedPayload);
        if (($header['alg'] ?? null) !== 'ES256' || ! is_array($header['x5c'] ?? null) || $header['x5c'] === []) {
            throw new BillingException('PURCHASE_VERIFICATION_FAILED', 'Apple signed data has an invalid certificate header.');
        }

        $certificates = array_map(function (mixed $certificate): string {
            if (! is_string($certificate) || base64_decode($certificate, true) === false) {
                throw new BillingException('PURCHASE_VERIFICATION_FAILED', 'Apple signed data contains an invalid certificate.');
            }

            return "-----BEGIN CERTIFICATE-----\n".chunk_split($certificate, 64, "\n")."-----END CERTIFICATE-----\n";
        }, $header['x5c']);
        $this->verifyCertificateChain($certificates);

        $publicKey = openssl_pkey_get_public($certificates[0]);
        $signature = $this->joseSignatureToDer($this->base64UrlDecode($encodedSignature));
        if ($publicKey === false || openssl_verify("$encodedHeader.$encodedPayload", $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new BillingException('PURCHASE_VERIFICATION_FAILED', 'Apple signed data signature is invalid.');
        }

        return $payload;
    }

    /** @param array<int, string> $certificates */
    private function verifyCertificateChain(array $certificates): void
    {
        foreach ($certificates as $certificate) {
            $parsed = openssl_x509_parse($certificate);
            $now = time();
            if (! is_array($parsed) || ($parsed['validFrom_time_t'] ?? $now + 1) > $now || ($parsed['validTo_time_t'] ?? $now - 1) < $now) {
                throw new BillingException('PURCHASE_VERIFICATION_FAILED', 'Apple signing certificate is outside its validity period.');
            }
        }
        for ($index = 0; $index < count($certificates) - 1; $index++) {
            $issuerKey = openssl_pkey_get_public($certificates[$index + 1]);
            if ($issuerKey === false || openssl_x509_verify($certificates[$index], $issuerKey) !== 1) {
                throw new BillingException('PURCHASE_VERIFICATION_FAILED', 'Apple signing certificate chain is invalid.');
            }
        }

        $trustedRoots = $this->trustedRoots();
        $last = $certificates[array_key_last($certificates)];
        foreach ($trustedRoots as $root) {
            $rootKey = openssl_pkey_get_public($root);
            if ($rootKey !== false && openssl_x509_verify($last, $rootKey) === 1) {
                return;
            }
            if (hash_equals(hash('sha256', $this->certificateDer($last)), hash('sha256', $this->certificateDer($root)))) {
                return;
            }
        }

        throw new BillingException('PURCHASE_VERIFICATION_FAILED', 'Apple signing certificate is not anchored to a configured Apple root certificate.');
    }

    /** @return array<int, string> */
    private function trustedRoots(): array
    {
        $location = trim((string) config('billing.apple.root_certificates_path'));
        if ($location === '') {
            throw new BillingException('STORE_CREDENTIALS_MISSING', 'Apple root certificates are not configured.', 503);
        }
        $paths = is_dir($location) ? glob(rtrim($location, '/').'/*.{cer,crt,pem}', GLOB_BRACE) : [$location];
        $roots = [];
        foreach ($paths ?: [] as $path) {
            $contents = is_file($path) ? file_get_contents($path) : false;
            if ($contents === false) {
                continue;
            }
            $roots[] = str_contains($contents, 'BEGIN CERTIFICATE')
                ? $contents
                : "-----BEGIN CERTIFICATE-----\n".chunk_split(base64_encode($contents), 64, "\n")."-----END CERTIFICATE-----\n";
        }
        if ($roots === []) {
            throw new BillingException('STORE_CREDENTIALS_MISSING', 'No readable Apple root certificate is configured.', 503);
        }

        return $roots;
    }

    /** @return array<string, mixed> */
    private function decodeJson(string $encoded): array
    {
        $decoded = json_decode($this->base64UrlDecode($encoded), true);
        if (! is_array($decoded)) {
            throw new BillingException('PURCHASE_VERIFICATION_FAILED', 'Apple signed data contains invalid JSON.');
        }

        return $decoded;
    }

    private function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        if ($decoded === false) {
            throw new BillingException('PURCHASE_VERIFICATION_FAILED', 'Apple signed data contains invalid encoding.');
        }

        return $decoded;
    }

    private function joseSignatureToDer(string $signature): string
    {
        if (strlen($signature) !== 64) {
            throw new BillingException('PURCHASE_VERIFICATION_FAILED', 'Apple signed data contains an invalid ECDSA signature.');
        }
        $r = $this->derInteger(substr($signature, 0, 32));
        $s = $this->derInteger(substr($signature, 32, 32));
        $sequence = $r.$s;

        return "\x30".$this->derLength(strlen($sequence)).$sequence;
    }

    private function derInteger(string $value): string
    {
        $value = ltrim($value, "\x00");
        $value = $value === '' ? "\x00" : $value;
        if ((ord($value[0]) & 0x80) !== 0) {
            $value = "\x00".$value;
        }

        return "\x02".$this->derLength(strlen($value)).$value;
    }

    private function derLength(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }
        $encoded = '';
        while ($length > 0) {
            $encoded = chr($length & 0xFF).$encoded;
            $length >>= 8;
        }

        return chr(0x80 | strlen($encoded)).$encoded;
    }

    private function certificateDer(string $pem): string
    {
        return (string) base64_decode(preg_replace('/-----[^-]+-----|\s+/', '', $pem) ?? '', true);
    }
}
