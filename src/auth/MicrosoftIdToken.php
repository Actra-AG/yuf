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
use InvalidArgumentException;
use Override;
use RuntimeException;
use SensitiveParameter;

/**
 * An ID token of the Microsoft identity platform (v2.0, `response_type=id_token`). The token is only accepted if
 * the algorithm is RS256 (a fixed value, never the one the token asks for), the signature matches a key of the tenant,
 * and `nbf`, `iat`, `exp`, `aud` (the client), `tid` (the tenant), `iss` (the v2.0 issuer of the tenant) and `nonce`
 * (the one of the login request) are right. The signing keys are cached in `$cacheDirectory`, one file per tenant.
 */
final class MicrosoftIdToken extends AuthWebToken
{
    private const string ISSUER = 'https://login.microsoftonline.com/{tenantId}/v2.0';
    private const string ALGORITHM = 'RS256';

    private readonly CachedKeySet $keySet;

    /**
     * @param string $cacheDirectory A writable directory for the key cache
     * @param ?JsonWebKeySetSource $keySetSource Where unknown keys are loaded from (default: Microsoft, over HTTPS)
     *
     * @throws InvalidArgumentException if the tenant ID is not a GUID or a domain name
     * @throws UnauthorizedException if the token is malformed or does not verify
     * @throws RuntimeException if an unknown signing key cannot be downloaded or the key cache cannot be written
     */
    public function __construct(
        private readonly string $tenantId,
        private readonly string $clientId,
        #[SensitiveParameter]
        private readonly string $ssoNonce,
        #[SensitiveParameter]
        string $jwtString,
        string $cacheDirectory,
        private readonly Clock $clock = new SystemClock(),
        ?JsonWebKeySetSource $keySetSource = null,
    ) {
        MicrosoftTenantId::assertValid(tenantId: $tenantId);
        $this->keySet = new CachedKeySet(
            cacheFilePath: rtrim(string: $cacheDirectory, characters: '/\\') . DIRECTORY_SEPARATOR
            . 'ssoMicrosoftKeys-' . $tenantId . '.json',
            source: $keySetSource ?? new MicrosoftKeySetSource(tenantId: $tenantId),
            clock: $clock,
        );
        parent::__construct(jwtString: $jwtString);
    }

    #[Override]
    public function getUserName(): string
    {
        $email = $this->readStringClaim(name: 'email');
        if ($email === null || $email === '') {
            throw new UnauthorizedException(message: 'Missing email');
        }

        return $email;
    }

    #[Override]
    protected function verify(): bool
    {
        $keyId = $this->readKeyId();
        $publicKey = $this->keySet->getKey(keyId: $keyId);
        if (openssl_verify(
            data: $this->encodedHeader . '.' . $this->encodedPayload,
            signature: $this->signature,
            public_key: $publicKey,
            algorithm: OPENSSL_ALGO_SHA256,
        ) !== 1) {
            throw new UnauthorizedException(message: 'Signature verification failed');
        }
        new IdTokenTimeClaimsValidator(clock: $this->clock)->assertValid(payload: $this->payload);
        $this->assertClaim(name: 'aud', expected: $this->clientId, message: 'Missing or invalid aud');
        $this->assertClaim(name: 'tid', expected: $this->tenantId, message: 'Missing or invalid tid');
        $this->assertClaim(
            name: 'iss',
            expected: str_replace(search: '{tenantId}', replace: $this->tenantId, subject: MicrosoftIdToken::ISSUER),
            message: 'Missing or invalid iss',
        );
        $this->assertClaim(name: 'nonce', expected: $this->ssoNonce, message: 'Invalid ssoNonce');

        return true;
    }

    /**
     * @throws UnauthorizedException
     */
    private function readKeyId(): string
    {
        $header = $this->header;
        if (!property_exists(object_or_class: $header, property: 'alg')
            || $header->alg !== MicrosoftIdToken::ALGORITHM) {
            throw new UnauthorizedException(message: 'Missing or invalid alg');
        }
        if (!property_exists(object_or_class: $header, property: 'kid') || !is_string(value: $header->kid)) {
            throw new UnauthorizedException(message: 'Missing kid');
        }

        return $header->kid;
    }

    /**
     * @throws UnauthorizedException
     */
    private function assertClaim(string $name, string $expected, string $message): void
    {
        $value = $this->readStringClaim(name: $name);
        if ($value === null || !hash_equals(known_string: $expected, user_string: $value)) {
            throw new UnauthorizedException(message: $message);
        }
    }

    private function readStringClaim(string $name): ?string
    {
        $payload = $this->payload;
        if (!property_exists(object_or_class: $payload, property: $name)) {
            return null;
        }
        $value = $payload->$name;

        return is_string(value: $value) ? $value : null;
    }
}
