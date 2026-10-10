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
 * and `nbf`, `iat`, `exp`, `aud` (the client), `tid` (the tenant), `oid` (a GUID), `iss` (the v2.0 issuer of the
 * tenant) and `nonce` (the one of the login request, at least 32 characters) are right. The signing keys are cached in
 * `$cacheDirectory`, one file per tenant.
 */
final class MicrosoftIdToken extends AuthWebToken
{
    private const string ISSUER = 'https://login.microsoftonline.com/{tenantId}/v2.0';
    private const string ALGORITHM = 'RS256';
    private const string GUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/Di';
    /** A nonce of the login request is never shorter (`SsoNonceCookie` creates 64 characters) */
    private const int MIN_NONCE_LENGTH = 32;

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

    /**
     * The ID of the user object in the tenant (claim `oid`, a GUID in lower case): stable for the account and the only
     * identity a login may be based on, together with `getTenantId()`.
     */
    public function getObjectId(): string
    {
        return strtolower(string: $this->readStringClaim(name: 'oid') ?? '');
    }

    /**
     * The ID of the tenant (claim `tid`, a GUID in lower case), the one the token was verified for.
     */
    public function getTenantId(): string
    {
        return strtolower(string: $this->readStringClaim(name: 'tid') ?? '');
    }

    /**
     * The email address claim, `null` if the token has none: for display only. The user can change it (tenant
     * administrators and guests set any address) and Microsoft does not verify it, so never identify a user by it.
     */
    public function getEmail(): ?string
    {
        $email = $this->readStringClaim(name: 'email');

        return $email === null || $email === '' ? null : $email;
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
        $this->assertGuidClaim(name: 'tid');
        $this->assertGuidClaim(name: 'oid');
        if (strlen(string: $this->ssoNonce) < MicrosoftIdToken::MIN_NONCE_LENGTH) {
            throw new UnauthorizedException(message: 'Invalid ssoNonce');
        }
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

    /**
     * @throws UnauthorizedException
     */
    private function assertGuidClaim(string $name): void
    {
        $value = $this->readStringClaim(name: $name);
        if ($value === null || preg_match(pattern: MicrosoftIdToken::GUID_PATTERN, subject: $value) !== 1) {
            throw new UnauthorizedException(message: 'Missing or invalid ' . $name);
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
