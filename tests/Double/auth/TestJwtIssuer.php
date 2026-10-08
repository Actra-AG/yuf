<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\auth;

use OpenSSLAsymmetricKey;
use OpenSSLCertificateSigningRequest;
use RuntimeException;

/**
 * Issues signed JWTs and the matching key set (JWKS with a self-signed certificate) with a generated RSA key, so
 * token tests need no network and no real key.
 */
final readonly class TestJwtIssuer
{
    public string $certificateBase64;
    private OpenSSLAsymmetricKey $privateKey;

    public function __construct(public string $keyId = 'key1')
    {
        $privateKey = openssl_pkey_new(
            options: ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA],
        );
        if ($privateKey === false) {
            throw new RuntimeException(message: 'Cannot generate a test key.');
        }
        // `openssl_csr_new()` takes the key by reference
        $keyForRequest = $privateKey;
        $csr = openssl_csr_new(distinguished_names: ['commonName' => 'yuf-test'], private_key: $keyForRequest);
        if (!$csr instanceof OpenSSLCertificateSigningRequest) {
            throw new RuntimeException(message: 'Cannot create a test certificate request.');
        }
        $certificate = openssl_csr_sign(
            csr: $csr,
            ca_certificate: null,
            private_key: $privateKey,
            days: 2,
            options: ['digest_alg' => 'sha256'],
        );
        $pem = '';
        if ($certificate === false || !openssl_x509_export(certificate: $certificate, output: $pem)
            || !is_string(value: $pem)) {
            throw new RuntimeException(message: 'Cannot create a test certificate.');
        }
        $this->privateKey = $privateKey;
        $this->certificateBase64 = str_replace(
            search: ['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\r", "\n"],
            replace: '',
            subject: $pem,
        );
    }

    /**
     * @param list<string> $additionalCertificates base64 DER certificates that follow the certificate of the key
     * @param array<string, mixed> $additionalFields further fields of the key (e.g. `d`)
     * @param list<array<string, mixed>> $additionalKeys further keys of the set
     */
    public function createKeySetJson(
        ?string $keyId = null,
        array $additionalCertificates = [],
        array $additionalFields = [],
        array $additionalKeys = [],
    ): string {
        return json_encode(
            value: [
                'keys' => [
                    [
                        'kty' => 'RSA',
                        'use' => 'sig',
                        'kid' => $keyId ?? $this->keyId,
                        'x5c' => [$this->certificateBase64, ...$additionalCertificates],
                    ] + $additionalFields,
                    ...$additionalKeys,
                ],
            ],
            flags: JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $header
     */
    public function createJwt(array $payload, array $header = ['alg' => 'RS256']): string
    {
        $header += ['kid' => $this->keyId];
        $signingInput = TestJwtIssuer::encodeSegment(data: $header) . '.'
            . TestJwtIssuer::encodeSegment(data: $payload);
        $signature = '';
        if (!openssl_sign(
            data: $signingInput,
            signature: $signature,
            private_key: $this->privateKey,
            algorithm: OPENSSL_ALGO_SHA256,
        ) || !is_string(value: $signature)) {
            throw new RuntimeException(message: 'Cannot sign the test token.');
        }

        return $signingInput . '.' . TestJwtIssuer::base64Url(raw: $signature);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function encodeSegment(array $data): string
    {
        return TestJwtIssuer::base64Url(raw: json_encode(value: $data, flags: JSON_THROW_ON_ERROR));
    }

    public static function base64Url(string $raw): string
    {
        return rtrim(string: strtr(string: base64_encode(string: $raw), from: '+/', to: '-_'), characters: '=');
    }
}
