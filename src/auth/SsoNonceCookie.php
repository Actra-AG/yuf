<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

use actra\yuf\core\HttpRequest;
use actra\yuf\exception\UnauthorizedException;
use InvalidArgumentException;

/**
 * The nonce of an SSO login, kept by yuf in its own short-lived cookie (never in the session, whose cookie stays
 * `SameSite=Strict`): created for the redirect to the identity provider (`issue()`), read and deleted once when the
 * token comes back (`consume()`). The cookie is `Secure`, `HttpOnly`, `SameSite=None`, limited to the path of the
 * callback and valid for ten minutes. A token without the cookie of the same browser is not accepted.
 *
 * @internal
 */
final readonly class SsoNonceCookie
{
    public const string NAME = '__Secure-yuf-sso-nonce';
    public const int MAX_AGE_IN_SECONDS = 600;
    /** Bytes of randomness of a nonce (the nonce has twice as many hex characters) */
    private const int NONCE_BYTES = 32;
    private const string NONCE_PATTERN = '/^[0-9a-f]{64}$/D';
    private const string PATH_PATTERN = '#^/[A-Za-z0-9._~!$&\'()*+,=:@%/-]*$#D';

    public function __construct(
        private HttpRequest $httpRequest,
        private SsoNonceCookieWriter $cookieWriter = new NativeSsoNonceCookieWriter(),
    ) {}

    /**
     * Creates a nonce (32 random bytes, hex), sends it in the cookie and returns it for the request to the provider.
     *
     * @param string $redirectUri The URI the provider returns to: its path is the path of the cookie
     *
     * @throws InvalidArgumentException if the URI has no usable path
     */
    public function issue(string $redirectUri): string
    {
        $path = SsoNonceCookie::readPath(redirectUri: $redirectUri);
        $nonce = bin2hex(string: random_bytes(length: SsoNonceCookie::NONCE_BYTES));
        $this->cookieWriter->set(
            name: SsoNonceCookie::NAME,
            value: $nonce,
            maxAgeInSeconds: SsoNonceCookie::MAX_AGE_IN_SECONDS,
            path: $path,
        );

        return $nonce;
    }

    /**
     * Returns the nonce of the request and deletes the cookie, whether or not the login succeeds: a nonce is used
     * once.
     *
     * @param string $redirectUri The URI of `issue()`
     *
     * @throws InvalidArgumentException if the URI has no usable path
     * @throws UnauthorizedException if the request has no (valid) nonce cookie
     */
    public function consume(string $redirectUri): string
    {
        $path = SsoNonceCookie::readPath(redirectUri: $redirectUri);
        $nonce = $this->httpRequest->getCookie(name: SsoNonceCookie::NAME);
        if ($nonce === null) {
            throw new UnauthorizedException(message: 'Missing SSO nonce cookie');
        }
        $this->cookieWriter->delete(name: SsoNonceCookie::NAME, path: $path);
        if (preg_match(pattern: SsoNonceCookie::NONCE_PATTERN, subject: $nonce) !== 1) {
            throw new UnauthorizedException(message: 'Invalid SSO nonce cookie');
        }

        return $nonce;
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function readPath(string $redirectUri): string
    {
        $path = parse_url(url: $redirectUri, component: PHP_URL_PATH);
        if (!is_string(value: $path) || preg_match(pattern: SsoNonceCookie::PATH_PATTERN, subject: $path) !== 1) {
            throw new InvalidArgumentException(message: 'The redirect URI of the SSO login needs an absolute path.');
        }

        return $path;
    }
}
