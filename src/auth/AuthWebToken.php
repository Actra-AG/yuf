<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

use actra\yuf\exception\UnauthorizedException;
use JsonException;
use RuntimeException;
use SensitiveParameter;
use stdClass;

/**
 * Extension point: a signed JWT (three base64url segments) of an identity provider that logs a user in
 * (`Authenticator::authWebTokenLogin()`). The constructor splits and decodes the token, then calls `verify()` of the
 * provider class; a token that does not verify never becomes an object. `verify()` must check the signature with a
 * fixed algorithm (never the one the token asks for) and every claim that ties the token to this application.
 */
abstract class AuthWebToken
{
    public readonly stdClass $header;
    public readonly stdClass $payload;
    protected readonly string $encodedHeader;
    protected readonly string $encodedPayload;
    protected readonly string $signature;

    /**
     * @throws UnauthorizedException if the token is malformed or does not verify
     * @throws RuntimeException if `verify()` needs a resource (e.g. the keys of the provider) that is not available
     */
    public function __construct(#[SensitiveParameter] string $jwtString)
    {
        $segments = explode(separator: '.', string: $jwtString);
        if (count(value: $segments) !== 3) {
            throw new UnauthorizedException(message: 'The JWT does not consist of three segments.');
        }
        [$this->encodedHeader, $this->encodedPayload, $encodedSignature] = $segments;
        $this->header = AuthWebToken::decodeJsonObject(
            json: AuthWebToken::decodeBase64Url(encoded: $this->encodedHeader),
        );
        $this->payload = AuthWebToken::decodeJsonObject(
            json: AuthWebToken::decodeBase64Url(encoded: $this->encodedPayload),
        );
        $this->signature = AuthWebToken::decodeBase64Url(encoded: $encodedSignature);
        if (!$this->verify()) {
            throw new UnauthorizedException(message: 'JWT verification failed');
        }
    }

    /**
     * @throws UnauthorizedException
     */
    protected static function decodeJsonObject(string $json): stdClass
    {
        try {
            $decoded = json_decode(json: $json, associative: false, flags: JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new UnauthorizedException(message: 'The JWT contains invalid JSON.');
        }
        if (!$decoded instanceof stdClass) {
            throw new UnauthorizedException(message: 'The JWT contains JSON that is not an object.');
        }

        return $decoded;
    }

    /**
     * @return bool `false` or an exception if the token is not valid
     *
     * @throws UnauthorizedException
     */
    abstract protected function verify(): bool;

    /**
     * @throws UnauthorizedException if the token has no valid user name
     */
    abstract public function getUserName(): string;

    private static function decodeBase64Url(string $encoded): string
    {
        $remainder = strlen(string: $encoded) % 4;
        if ($remainder !== 0) {
            $encoded .= str_repeat(string: '=', times: 4 - $remainder);
        }
        $decoded = base64_decode(string: strtr(string: $encoded, from: '-_', to: '+/'), strict: true);
        if ($decoded === false) {
            throw new UnauthorizedException(message: 'The JWT contains a segment that is not valid base64url.');
        }

        return $decoded;
    }
}
