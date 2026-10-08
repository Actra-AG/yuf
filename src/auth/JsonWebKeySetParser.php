<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

use actra\yuf\exception\UnauthorizedException;
use JsonException;
use OpenSSLAsymmetricKey;
use OpenSSLCertificate;

/**
 * Reads the public keys of a JWKS whose keys carry an X.509 certificate chain (`x5c`), as the Microsoft identity
 * platform publishes it. The chain is checked for consistency (every certificate is signed by the next one, the last
 * one is self-signed); it is not anchored in a trusted root, the key set is trusted because it was downloaded over
 * verified HTTPS from the identity provider. Keys without `x5c` are skipped.
 *
 * @internal
 */
final readonly class JsonWebKeySetParser
{
    /**
     * @return non-empty-array<string, OpenSSLAsymmetricKey> The public keys by key ID
     *
     * @throws UnauthorizedException if the JSON is invalid, a key is not acceptable or no usable key is left
     */
    public static function parse(string $json): array
    {
        try {
            $keySet = json_decode(json: $json, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new UnauthorizedException(message: 'The key set is not valid JSON.');
        }
        if (!is_array(value: $keySet) || !array_key_exists(key: 'keys', array: $keySet)
            || !is_array(value: $keySet['keys'])) {
            throw new UnauthorizedException(message: 'The key set has no "keys" list.');
        }
        $keys = [];
        foreach ($keySet['keys'] as $jsonWebKey) {
            if (!is_array(value: $jsonWebKey) || !array_key_exists(key: 'kid', array: $jsonWebKey)
                || !is_string(value: $jsonWebKey['kid'])) {
                throw new UnauthorizedException(message: 'The key set contains a key without key ID.');
            }
            $publicKey = JsonWebKeySetParser::parseKey(jsonWebKey: $jsonWebKey);
            if ($publicKey !== null) {
                $keys[$jsonWebKey['kid']] = $publicKey;
            }
        }
        if ($keys === []) {
            throw new UnauthorizedException(message: 'The key set contains no usable key.');
        }

        return $keys;
    }

    /**
     * @param array<array-key, mixed> $jsonWebKey
     *
     * @throws UnauthorizedException
     */
    private static function parseKey(array $jsonWebKey): ?OpenSSLAsymmetricKey
    {
        if (array_key_exists(key: 'd', array: $jsonWebKey)) {
            throw new UnauthorizedException(message: 'Failed to parse JWK: RSA private key is not supported');
        }
        if (!array_key_exists(key: 'x5c', array: $jsonWebKey)) {
            return null;
        }
        $encodedCertificates = $jsonWebKey['x5c'];
        if (!is_array(value: $encodedCertificates) || $encodedCertificates === []) {
            throw new UnauthorizedException(message: 'Failed to parse JWK: invalid certificate chain');
        }
        $certificates = [];
        foreach ($encodedCertificates as $encodedCertificate) {
            $certificates[] = JsonWebKeySetParser::readCertificate(encodedCertificate: $encodedCertificate);
        }
        JsonWebKeySetParser::assertValidChain(certificates: $certificates);
        $publicKey = openssl_pkey_get_public(public_key: $certificates[0]);
        if ($publicKey === false) {
            throw new UnauthorizedException(message: 'Failed to parse JWK: invalid public key');
        }

        return $publicKey;
    }

    /**
     * @throws UnauthorizedException
     */
    private static function readCertificate(mixed $encodedCertificate): OpenSSLCertificate
    {
        if (!is_string(value: $encodedCertificate)) {
            throw new UnauthorizedException(message: 'Failed to parse JWK: invalid certificate');
        }
        // A certificate that cannot be read is reported as warning, the failure is handled here
        set_error_handler(callback: static fn(): bool => true);
        try {
            $certificate = openssl_x509_read(
                certificate: '-----BEGIN CERTIFICATE-----' . PHP_EOL
                . chunk_split(string: $encodedCertificate, length: 64, separator: PHP_EOL)
                . '-----END CERTIFICATE-----' . PHP_EOL,
            );
        } finally {
            restore_error_handler();
        }
        if ($certificate === false) {
            throw new UnauthorizedException(message: 'Failed to parse JWK: invalid certificate');
        }

        return $certificate;
    }

    /**
     * Every certificate is signed by the next one, the last one is self-signed (root).
     *
     * @param non-empty-list<OpenSSLCertificate> $certificates
     *
     * @throws UnauthorizedException
     */
    private static function assertValidChain(array $certificates): void
    {
        $signer = null;
        foreach (array_reverse(array: $certificates) as $certificate) {
            if (openssl_x509_verify(certificate: $certificate, public_key: $signer ?? $certificate) !== 1) {
                throw new UnauthorizedException(
                    message: $signer === null ? 'Invalid Root Certificate' : 'Invalid Certificate',
                );
            }
            $signer = $certificate;
        }
    }
}
