<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;
use actra\yuf\exception\UnauthorizedException;
use OpenSSLAsymmetricKey;
use Override;
use stdClass;

class MicrosoftIdToken extends AuthWebToken
{
    private const string PUBLIC_KEYS_PATH = 'https://login.microsoftonline.com/{tenantId}/discovery/keys';

    public function __construct(
        private readonly string $tenantId,
        private readonly string $clientId,
        private readonly string $ssoNonce,
        string $jwtString,
        private readonly string $cacheDirectory,
        private readonly Clock $clock = new SystemClock(),
    ) {
        parent::__construct(jwtString: $jwtString);
    }

    #[Override]
    public function getUserName(): string
    {
        return $this->payload->email;
    }

    #[Override]
    protected function verify(): bool
    {
        $header = $this->header;
        if (!property_exists(object_or_class: $header, property: 'alg') || $header->alg !== 'RS256') {
            throw new UnauthorizedException(message: 'Missing or invalid alg');
        }
        if (!property_exists(object_or_class: $header, property: 'kid')) {
            throw new UnauthorizedException(message: 'Missing kid');
        }
        $cachePath = $this->cacheDirectory . 'ssoMicrosoftKeys.json';
        $publicKeysPath = str_replace(
            search: '{tenantId}',
            replace: $this->tenantId,
            subject: MicrosoftIdToken::PUBLIC_KEYS_PATH,
        );
        if (!file_exists(filename: $cachePath)) {
            copy(from: $publicKeysPath, to: $cachePath);
        }
        $publicKeySet = MicrosoftIdToken::parseKeySet(sourceFilePath: $cachePath);
        if (!array_key_exists(key: $header->kid, array: $publicKeySet)) {
            copy(from: $publicKeysPath, to: $cachePath);
        }
        $publicKeySet = MicrosoftIdToken::parseKeySet(sourceFilePath: $cachePath);
        $publicKey = $publicKeySet[$header->kid];
        if (openssl_verify(
            data: "$this->base64Header.$this->base64Payload",
            signature: $this->secret,
            public_key: $publicKey,
            algorithm: OPENSSL_ALGO_SHA256,
        ) !== 1) {
            throw new UnauthorizedException(message: 'Signature verification failed');
        }
        $payload = $this->payload;
        new IdTokenTimeClaimsValidator(clock: $this->clock)->assertValid(payload: $payload);
        if (!property_exists(object_or_class: $payload, property: 'aud') || $payload->aud !== $this->clientId) {
            throw new UnauthorizedException(message: 'Missing or invalid aud');
        }
        if (!property_exists(object_or_class: $payload, property: 'tid') || $payload->tid !== $this->tenantId) {
            throw new UnauthorizedException(message: 'Missing or invalid tid');
        }
        if (!property_exists(object_or_class: $payload, property: 'nonce') || $payload->nonce !== $this->ssoNonce) {
            throw new UnauthorizedException(message: 'Invalid ssoNonce');
        }

        return true;
    }

    protected static function parseKeySet(string $sourceFilePath): array
    {
        $json = AuthWebToken::jsonDecode(input: file_get_contents(filename: $sourceFilePath));
        $keys = [];
        foreach ($json->keys as $key) {
            $keys[$key->kid] = MicrosoftIdToken::parseKey(source: $key);
        }
        if (count(value: $keys) === 0) {
            throw new UnauthorizedException(message: 'Failed to parse key file:' . $sourceFilePath);
        }

        return $keys;
    }

    private static function parseKey(stdClass $source): OpenSSLAsymmetricKey
    {
        if (property_exists(object_or_class: $source, property: 'd')) {
            throw new UnauthorizedException(message: 'Failed to parse JWK: RSA private key is not supported');
        }
        $certificates = [];
        foreach ($source->x5c as $x5c) {
            $certificates[] = openssl_x509_read(
                certificate: implode(separator: PHP_EOL, array: [
                    '-----BEGIN CERTIFICATE-----',
                    chunk_split(string: $x5c, length: 64, separator: PHP_EOL) . '-----END CERTIFICATE-----',
                    '',
                ]),
            );
        }
        // Verify validity of certificate chain (each one is signed by the next, except root cert)
        for ($i = 0; $i < count(value: $certificates); $i++) {
            if ($i === count(value: $certificates) - 1) {
                if (openssl_x509_verify(certificate: $certificates[$i], public_key: $certificates[$i]) !== 1) {
                    throw new UnauthorizedException(message: 'Invalid Root Certificate');
                }
            } elseif (openssl_x509_verify(certificate: $certificates[$i], public_key: $certificates[$i + 1]) !== 1) {
                throw new UnauthorizedException(message: 'Invalid Certificate');
            }
        }

        return openssl_pkey_get_public(public_key: $certificates[0]);
    }
}
